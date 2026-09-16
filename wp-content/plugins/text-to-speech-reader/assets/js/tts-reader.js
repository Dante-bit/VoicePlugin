/**
 * Text to Speech Reader — front-end logic v2.0
 *
 * Hỗ trợ ba engine:
 *   1. "google"    — Google Translate TTS Online (mặc định), phát âm chuẩn tiếng Việt
 *                    tự nhiên trên MỌI trình duyệt/hệ điều hành, không cần cài thêm.
 *   2. "auto"      — Ưu tiên giọng tiếng Việt tích hợp hệ thống/trình duyệt,
 *                    nếu không tìm thấy thì tự động chuyển sang engine google.
 *   3. "webspeech" — Chỉ dùng Web Speech API (hành vi giống plugin cũ).
 *
 * Với Google TTS engine:
 *   - Văn bản được chia nhỏ thành các câu ≤ 160 ký tự.
 *   - Audio được tải qua WordPress AJAX proxy (tránh CORS).
 *   - Tải trước câu tiếp theo (pre-buffer) để đọc mượt mà, không giật.
 *   - Hỗ trợ đầy đủ: Play / Pause / Resume / Stop + thay đổi tốc độ.
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------
	 * CẤU HÌNH (nhận từ PHP qua wp_localize_script)
	 * ------------------------------------------------------------------ */
	var cfg = window.ttsReaderConfig || {
		lang:     'vi-VN',
		rate:     1,
		engine:   'google',
		proxyUrl: '',
		i18n: {
			play:              'Nghe bài viết',
			pause:             'Tạm dừng',
			resume:            'Tiếp tục',
			stop:              'Dừng',
			loading:           'Đang tải...',
			unsupported:       'Trình duyệt của bạn không hỗ trợ đọc văn bản.',
			noVietnameseVoice: 'Không tìm thấy giọng đọc tiếng Việt trên thiết bị này.',
			notGoogleVoice:    'Đang dùng giọng hệ thống (không phải Google).',
			error:             'Không thể tải giọng đọc. Vui lòng kiểm tra kết nối mạng.',
		},
	};

	/* ------------------------------------------------------------------
	 * TRẠNG THÁI TOÀN CỤC
	 * ------------------------------------------------------------------ */
	var state = {
		// engine đang chạy thực sự (sau khi auto-detect): 'google' | 'webspeech'
		activeEngine: cfg.engine === 'auto' ? 'google' : cfg.engine,
		playing:      false,
		paused:       false,
		currentWrapper: null,

		// — Google TTS —
		chunks:        [],   // mảng các đoạn văn bản đã chia
		chunkIndex:    0,    // chunk đang phát
		audioEl:       null, // HTMLAudioElement đang dùng
		nextAudioEl:   null, // pre-buffer câu tiếp theo

		// — Web Speech API —
		synth:           null,
		utterance:       null,
		cachedVoices:    [],
		warnedNoViet:    false,
		warnedNotGoogle: false,
	};

	/* ==================================================================
	 * PHẦN 1: GOOGLE TTS ENGINE
	 * ================================================================== */

	/**
	 * Chia văn bản thành mảng các đoạn ngắn ≤ maxLen ký tự,
	 * ưu tiên cắt ở dấu câu để câu nghe tự nhiên.
	 */
	function splitText( text, maxLen ) {
		maxLen = maxLen || 160;
		var results = [];

		// Chia theo dấu xuống dòng trước.
		var lines = text.replace( /\r\n/g, '\n' ).split( /\n+/ );

		lines.forEach( function ( line ) {
			line = line.trim();
			if ( ! line ) return;

			if ( line.length <= maxLen ) {
				results.push( line );
				return;
			}

			// Chia theo dấu câu trong dòng dài.
			var segments = line.split( /(?<=[.!?;,。！？；，])\s+/ );
			var buf = '';

			segments.forEach( function ( seg ) {
				if ( ! seg.trim() ) return;
				if ( buf.length + seg.length + 1 > maxLen && buf ) {
					results.push( buf.trim() );
					buf = seg;
				} else {
					buf = buf ? buf + ' ' + seg : seg;
				}
			} );

			if ( buf.trim() ) {
				results.push( buf.trim() );
			}
		} );

		return results.filter( function ( s ) { return s.length > 0; } );
	}

	/**
	 * Lấy URL audio Google TTS cho một đoạn văn bản,
	 * thông qua AJAX proxy WordPress.
	 */
	function googleTtsUrl( text, lang ) {
		var langCode = ( lang || 'vi-VN' ).split( '-' )[0]; // "vi" từ "vi-VN"
		var base = cfg.proxyUrl || '';
		if ( ! base ) {
			// fallback: gọi trực tiếp Google (có thể bị CORS trên một số trình duyệt)
			return 'https://translate.google.com/translate_tts'
				+ '?ie=UTF-8&tl=' + encodeURIComponent( langCode )
				+ '&client=tw-ob&q=' + encodeURIComponent( text );
		}
		return base
			+ '?action=tts_reader_proxy'
			+ '&l=' + encodeURIComponent( langCode )
			+ '&t=' + encodeURIComponent( text );
	}

	/**
	 * Tạo HTMLAudioElement cho một đoạn văn bản.
	 * @return {HTMLAudioElement}
	 */
	function makeAudio( text ) {
		var audio = new Audio( googleTtsUrl( text, cfg.lang ) );
		audio.preload = 'auto';
		// Áp dụng tốc độ đọc.
		audio.playbackRate = state.currentRate || 1;
		return audio;
	}

	/**
	 * Phát chunk hiện tại và pre-buffer chunk kế tiếp.
	 */
	function playCurrentChunk( wrapper ) {
		if ( state.chunkIndex >= state.chunks.length ) {
			// Đã đọc hết tất cả các chunk.
			stopGoogle( wrapper );
			setWrapperState( wrapper, 'idle' );
			return;
		}

		var audio;
		// Dùng pre-buffer nếu đã có.
		if ( state.nextAudioEl ) {
			audio = state.nextAudioEl;
			state.nextAudioEl = null;
		} else {
			audio = makeAudio( state.chunks[ state.chunkIndex ] );
		}

		state.audioEl = audio;
		audio.playbackRate = state.currentRate || 1;

		audio.onended = function () {
			if ( ! state.playing ) return;
			state.chunkIndex++;
			playCurrentChunk( wrapper );
		};

		audio.onerror = function () {
			if ( ! state.playing ) return;
			// Bỏ qua chunk lỗi, chuyển sang chunk tiếp.
			state.chunkIndex++;
			playCurrentChunk( wrapper );
		};

		audio.play().catch( function () {
			// Autoplay bị chặn → yêu cầu tương tác người dùng đã đủ từ click.
		} );

		// Pre-buffer chunk tiếp theo.
		var nextIdx = state.chunkIndex + 1;
		if ( nextIdx < state.chunks.length ) {
			state.nextAudioEl = makeAudio( state.chunks[ nextIdx ] );
		}
	}

	function stopGoogle( wrapper ) {
		if ( state.audioEl ) {
			state.audioEl.pause();
			state.audioEl.src = '';
			state.audioEl = null;
		}
		if ( state.nextAudioEl ) {
			state.nextAudioEl.src = '';
			state.nextAudioEl = null;
		}
		state.playing    = false;
		state.paused     = false;
		state.chunkIndex = 0;
		state.chunks     = [];
	}

	function startGoogleTTS( wrapper, text ) {
		stopGoogle( wrapper );

		state.chunks     = splitText( text );
		state.chunkIndex = 0;
		state.playing    = true;
		state.paused     = false;
		state.currentWrapper = wrapper;
		state.currentRate    = getRate( wrapper );

		setWrapperState( wrapper, 'playing' );
		playCurrentChunk( wrapper );
	}

	function pauseGoogleTTS( wrapper ) {
		if ( state.audioEl ) {
			state.audioEl.pause();
		}
		state.paused = true;
		state.playing = false;
		setWrapperState( wrapper, 'paused' );
	}

	function resumeGoogleTTS( wrapper ) {
		if ( state.audioEl ) {
			state.audioEl.playbackRate = state.currentRate || 1;
			state.audioEl.play().catch( function () {} );
			state.paused = false;
			state.playing = true;
			setWrapperState( wrapper, 'playing' );
		} else {
			// Nếu audio element đã mất, phát lại từ chunk hiện tại.
			state.paused  = false;
			state.playing = true;
			setWrapperState( wrapper, 'playing' );
			playCurrentChunk( wrapper );
		}
	}

	/* ==================================================================
	 * PHẦN 2: WEB SPEECH API ENGINE
	 * ================================================================== */

	function initWebSpeech() {
		if ( ! ( 'speechSynthesis' in window ) ) return false;
		state.synth = window.speechSynthesis;
		return true;
	}

	function refreshVoices() {
		var list = state.synth ? state.synth.getVoices() : [];
		if ( list && list.length ) state.cachedVoices = list;
	}

	function getVoicesAsync( timeoutMs ) {
		return new Promise( function ( resolve ) {
			if ( ! state.synth ) { resolve( [] ); return; }
			var existing = state.synth.getVoices();
			if ( existing && existing.length ) {
				state.cachedVoices = existing;
				resolve( existing );
				return;
			}
			var settled = false;
			function finish( list ) {
				if ( settled ) return;
				settled = true;
				if ( list && list.length ) state.cachedVoices = list;
				if ( typeof state.synth.removeEventListener === 'function' ) {
					state.synth.removeEventListener( 'voiceschanged', onChange );
				}
				resolve( state.cachedVoices );
			}
			function onChange() { finish( state.synth.getVoices() ); }
			if ( typeof state.synth.addEventListener === 'function' ) {
				state.synth.addEventListener( 'voiceschanged', onChange );
			} else {
				state.synth.onvoiceschanged = onChange;
			}
			setTimeout( function () { finish( state.synth.getVoices() ); }, timeoutMs || 1500 );
		} );
	}

	function findVoiceForLang( langCode ) {
		if ( ! state.cachedVoices.length ) refreshVoices();
		if ( ! state.cachedVoices.length ) return null;

		var lower    = ( langCode || 'vi-VN' ).toLowerCase();
		var prefix   = lower.split( '-' )[0];
		var candidates = state.cachedVoices.filter( function ( v ) {
			return v.lang && v.lang.toLowerCase().indexOf( prefix ) === 0;
		} );
		if ( ! candidates.length ) return null;

		var google = candidates.find( function ( v ) { return /google/i.test( v.name ); } );
		if ( google ) return google;

		var exact = candidates.find( function ( v ) { return v.lang.toLowerCase() === lower; } );
		return exact || candidates[0];
	}

	function startWebSpeech( wrapper, text ) {
		if ( ! initWebSpeech() ) return;
		if ( state.synth.speaking || state.synth.paused ) state.synth.cancel();

		setWrapperState( wrapper, 'playing' );
		state.playing = true;
		state.paused  = false;
		state.currentWrapper = wrapper;
		state.currentRate    = getRate( wrapper );

		getVoicesAsync().then( function () {
			if ( state.currentWrapper !== wrapper ) return;

			var utter   = new SpeechSynthesisUtterance( text );
			utter.lang  = cfg.lang || 'vi-VN';
			utter.rate  = state.currentRate || 1;

			var voice = findVoiceForLang( cfg.lang );
			if ( voice ) {
				utter.voice = voice;
				utter.lang  = voice.lang;
				var isGoogle = /google/i.test( voice.name );
				if ( ! isGoogle && ! state.warnedNotGoogle ) {
					state.warnedNotGoogle = true;
					showNotice( wrapper, cfg.i18n.notGoogleVoice );
				}
			} else if ( cfg.lang.toLowerCase().indexOf( 'vi' ) === 0 && ! state.warnedNoViet ) {
				// Không tìm thấy giọng tiếng Việt.
				if ( cfg.engine === 'auto' ) {
					// Chuyển sang Google TTS online.
					state.synth.cancel();
					state.activeEngine = 'google';
					startGoogleTTS( wrapper, text );
					return;
				}
				state.warnedNoViet = true;
				showNotice( wrapper, cfg.i18n.noVietnameseVoice );
			}

			utter.onend = function () {
				state.playing = false;
				state.utterance = null;
				setWrapperState( wrapper, 'idle' );
				state.currentWrapper = null;
			};
			utter.onerror = function () {
				state.playing = false;
				state.utterance = null;
				setWrapperState( wrapper, 'idle' );
				state.currentWrapper = null;
			};

			state.utterance = utter;
			state.synth.speak( utter );
			setWrapperState( wrapper, 'playing' );
		} );
	}

	function stopWebSpeech( wrapper ) {
		if ( state.synth ) state.synth.cancel();
		state.playing    = false;
		state.paused     = false;
		state.utterance  = null;
	}

	/* ==================================================================
	 * PHẦN 3: ĐIỀU PHỐI ENGINE (chọn engine phù hợp)
	 * ================================================================== */

	function stopAll( wrapper ) {
		stopGoogle( wrapper );
		stopWebSpeech( wrapper );
		if ( wrapper ) setWrapperState( wrapper, 'idle' );
		state.currentWrapper = null;
	}

	function startReading( wrapper, text ) {
		var engine = cfg.engine;

		if ( engine === 'google' ) {
			state.activeEngine = 'google';
			startGoogleTTS( wrapper, text );
		} else if ( engine === 'webspeech' ) {
			state.activeEngine = 'webspeech';
			if ( ! initWebSpeech() ) {
				showNotice( wrapper, cfg.i18n.unsupported );
				setWrapperState( wrapper, 'idle' );
				return;
			}
			startWebSpeech( wrapper, text );
		} else {
			// auto: thử Web Speech trước (initWebSpeech + tìm giọng Việt ở trong startWebSpeech)
			state.activeEngine = 'auto';
			if ( ! initWebSpeech() ) {
				// Không hỗ trợ Web Speech → dùng Google TTS
				state.activeEngine = 'google';
				startGoogleTTS( wrapper, text );
			} else {
				// Kiểm tra nhanh xem có giọng Việt không trước khi bắt đầu
				refreshVoices();
				var voice = findVoiceForLang( cfg.lang );
				if ( voice ) {
					state.activeEngine = 'webspeech';
					startWebSpeech( wrapper, text );
				} else {
					// Chưa load xong voices, thử Web Speech (nó sẽ tự fallback sang Google nếu cần)
					state.activeEngine = 'webspeech';
					startWebSpeech( wrapper, text );
				}
			}
		}
	}

	function togglePlayPause( wrapper ) {
		// Nếu wrapper khác đang chạy → dừng nó trước.
		if ( state.currentWrapper && state.currentWrapper !== wrapper ) {
			stopAll( state.currentWrapper );
		}

		// Đang tạm dừng → tiếp tục.
		if ( state.currentWrapper === wrapper && state.paused ) {
			if ( state.activeEngine === 'google' ) {
				resumeGoogleTTS( wrapper );
			} else {
				if ( state.synth ) {
					state.synth.resume();
					state.paused  = false;
					state.playing = true;
					setWrapperState( wrapper, 'playing' );
				}
			}
			return;
		}

		// Đang phát → tạm dừng.
		if ( state.currentWrapper === wrapper && state.playing ) {
			if ( state.activeEngine === 'google' ) {
				pauseGoogleTTS( wrapper );
			} else {
				if ( state.synth ) {
					state.synth.pause();
					state.paused  = true;
					state.playing = false;
					setWrapperState( wrapper, 'paused' );
				}
			}
			return;
		}

		// Bắt đầu phát mới.
		var targetSelector = wrapper.getAttribute( 'data-tts-target' );
		var targetEl = targetSelector ? document.querySelector( targetSelector ) : null;
		if ( ! targetEl ) return;

		var text = extractText( targetEl );
		if ( ! text ) return;

		startReading( wrapper, text );
	}

	/* ==================================================================
	 * PHẦN 4: CÁC HÀM TIỆN ÍCH
	 * ================================================================== */

	function extractText( el ) {
		var clone = el.cloneNode( true );
		var toRemove = clone.querySelectorAll( '.tts-reader-wrapper, script, style, .tts-reader-source' );
		toRemove.forEach( function ( n ) { n.parentNode.removeChild( n ); } );
		return clone.textContent.replace( /\s+/g, ' ' ).trim();
	}

	function getRate( wrapper ) {
		var sel = wrapper ? wrapper.querySelector( '.tts-reader-speed' ) : null;
		return sel ? parseFloat( sel.value ) : ( cfg.rate || 1 );
	}

	function setWrapperState( wrapper, st ) {
		var stopBtn = wrapper.querySelector( '.tts-reader-stop' );
		var icon    = wrapper.querySelector( '.tts-icon-play' );
		var labelEl = wrapper.querySelector( '.tts-label' );
		var waveEl  = wrapper.querySelector( '.tts-wave' );

		wrapper.classList.remove( 'is-playing', 'is-paused' );

		if ( st === 'playing' ) {
			wrapper.classList.add( 'is-playing' );
			if ( icon ) icon.setAttribute( 'data-state', 'playing' );
			if ( labelEl ) labelEl.textContent = cfg.i18n.pause;
			if ( stopBtn ) stopBtn.style.display = 'inline-flex';
			if ( waveEl ) waveEl.style.display = 'flex';
		} else if ( st === 'paused' ) {
			wrapper.classList.add( 'is-paused' );
			if ( icon ) icon.setAttribute( 'data-state', 'paused' );
			if ( labelEl ) labelEl.textContent = cfg.i18n.resume;
			if ( stopBtn ) stopBtn.style.display = 'inline-flex';
			if ( waveEl ) waveEl.style.display = 'none';
		} else {
			if ( icon ) icon.setAttribute( 'data-state', 'idle' );
			if ( labelEl ) labelEl.textContent = cfg.i18n.play;
			if ( stopBtn ) stopBtn.style.display = 'none';
			if ( waveEl ) waveEl.style.display = 'none';
		}
	}

	function showNotice( wrapper, message ) {
		var existing = wrapper.querySelector( '.tts-reader-notice' );
		if ( existing ) {
			existing.textContent = message;
			return;
		}
		var notice = document.createElement( 'div' );
		notice.className   = 'tts-reader-notice';
		notice.textContent = message;
		wrapper.appendChild( notice );
	}

	/* ==================================================================
	 * PHẦN 5: KHỞI TẠO
	 * ================================================================== */

	// Tiền tải danh sách voices Web Speech khi script khởi động.
	if ( ( 'speechSynthesis' in window ) && cfg.engine !== 'google' ) {
		state.synth = window.speechSynthesis;
		getVoicesAsync();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrappers = document.querySelectorAll( '.tts-reader-wrapper' );

		wrappers.forEach( function ( wrapper ) {
			var playBtn = wrapper.querySelector( '.tts-reader-play' );
			var stopBtn = wrapper.querySelector( '.tts-reader-stop' );
			var speedSel = wrapper.querySelector( '.tts-reader-speed' );

			if ( playBtn ) {
				playBtn.addEventListener( 'click', function () {
					togglePlayPause( wrapper );
				} );
			}

			if ( stopBtn ) {
				stopBtn.addEventListener( 'click', function () {
					stopAll( wrapper );
				} );
			}

			// Thay đổi tốc độ ngay lập tức khi đang phát.
			if ( speedSel ) {
				speedSel.addEventListener( 'change', function () {
					state.currentRate = parseFloat( speedSel.value );
					if ( state.activeEngine === 'google' && state.audioEl ) {
						state.audioEl.playbackRate = state.currentRate;
					} else if ( state.synth && state.utterance ) {
						// Web Speech không cho thay tốc độ giữa chừng — restart.
						var currentWrapper = state.currentWrapper;
						if ( currentWrapper === wrapper && state.playing ) {
							var sel  = wrapper.querySelector( '[data-tts-target]' );
							var tId  = wrapper.getAttribute( 'data-tts-target' );
							var tEl  = tId ? document.querySelector( tId ) : null;
							if ( tEl ) {
								var txt = extractText( tEl );
								stopWebSpeech( wrapper );
								startWebSpeech( wrapper, txt );
							}
						}
					}
				} );
			}
		} );

		window.addEventListener( 'beforeunload', function () {
			stopAll( state.currentWrapper );
		} );
	} );

} )();
