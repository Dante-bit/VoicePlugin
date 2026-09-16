/**
 * Text to Speech Reader AI Pro — Admin Dashboard Logic v3.0
 * Điều khiển Studio thử nghiệm giọng đọc trực tiếp, visualizer sóng âm,
 * quét giọng đọc của hệ điều hành, và bộ tạo mã shortcode tương tác.
 */
( function () {
	'use strict';

	var config = window.ttsAdminConfig || {
		proxyUrl:     '',
		defaultVoice: 'female_standard',
		defaultRate:  1,
		defaultLang:  'vi-VN',
		testSample:   'Xin chào quý độc giả! Đây là hệ thống đọc bài viết tự động bằng trí tuệ nhân tạo.',
		i18n: {
			copied:     'Đã sao chép shortcode vào bộ nhớ tạm!',
			copyFail:   'Không thể sao chép, vui lòng nhấn Ctrl+C',
			playing:    'Đang phát âm thanh...',
			paused:     'Đã tạm dừng',
			stopped:    'Đã dừng phát',
			btnPlay:    '▶ Phát thử nghiệm',
			btnPause:   '⏸ Tạm dừng',
			checking:   'Đang quét danh sách giọng đọc...',
			foundVoice: 'Tìm thấy giọng đọc tiếng Việt khả dụng:',
			noVoice:    'Không tìm thấy giọng tiếng Việt cục bộ. Google TTS Online luôn hoạt động tốt!',
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		initVoiceStudio();
		initShortcodeGenerator();
		initBrowserVoiceScanner();
	} );

	/* ==================================================================
	 * 1. AI VOICE STUDIO & REAL-TIME HIGHLIGHT SIMULATOR
	 * ================================================================== */
	function initVoiceStudio() {
		var voiceSelect  = document.getElementById( 'tts-studio-voice-select' );
		var speedSelect  = document.getElementById( 'tts-studio-speed-select' );
		var textarea     = document.getElementById( 'tts-studio-textarea' );
		var previewBox   = document.getElementById( 'tts-studio-highlight-preview' );
		var playBtn      = document.getElementById( 'tts-studio-play-btn' );
		var stopBtn      = document.getElementById( 'tts-studio-stop-btn' );
		var equalizer    = document.getElementById( 'tts-studio-equalizer' );
		var statusText   = document.getElementById( 'tts-studio-status' );

		if ( ! playBtn || ! textarea || ! previewBox ) return;

		var state = {
			playing:    false,
			paused:     false,
			chunks:     [],
			chunkIndex: 0,
			audio:      null,
			synthUtter: null,
		};

		var allPreviewWords = [];
		var chunkWordMaps = [];
		var animFrameId = null;

		// Tách câu & từng từ, cập nhật giao diện xem trước
		function updatePreviewSpans() {
			var text = textarea.value.trim();
			if ( ! text ) {
				previewBox.innerHTML = '<em>' + ( config.testSample || 'Vui lòng nhập văn bản cần đọc.' ) + '</em>';
				state.chunks = [];
				allPreviewWords = [];
				chunkWordMaps = [];
				return;
			}

			// Tách theo câu
			var rawChunks = text.split( /(?<=[.!?;,。！？；\n])\s+/ );
			state.chunks = rawChunks.filter( function ( s ) { return s.trim().length > 0; } );
			if ( ! state.chunks.length ) state.chunks = [ text ];

			// Tách từng từ để tô sáng 1 từ duy nhất
			var rawWords = text.split( /\s+/ ).filter( function ( w ) { return w.length > 0; } );
			allPreviewWords = [];
			var html = '';

			rawWords.forEach( function ( word, idx ) {
				html += '<span class="tts-preview-word" data-word-idx="' + idx + '">' + escapeHtml( word ) + '</span> ';
				allPreviewWords.push( {
					idx: idx,
					text: word,
					len: word.length
				} );
			} );
			previewBox.innerHTML = html;

			// Ánh xạ chunks sang từ
			chunkWordMaps = [];
			var cursor = 0;
			state.chunks.forEach( function ( cText ) {
				var words = cText.trim().split( /\s+/ ).filter( function ( w ) { return w.length > 0; } );
				var start = cursor;
				var end = Math.min( allPreviewWords.length - 1, start + words.length - 1 );
				cursor = end + 1;

				var totalChars = 0;
				var cWords = [];
				for ( var i = start; i <= end; i++ ) {
					if ( allPreviewWords[i] ) {
						totalChars += allPreviewWords[i].len;
						cWords.push( allPreviewWords[i] );
					}
				}
				chunkWordMaps.push( {
					start: start,
					end: end,
					totalChars: totalChars || 1,
					words: cWords
				} );
			} );
		}

		updatePreviewSpans();
		textarea.addEventListener( 'input', function () {
			if ( ! state.playing ) updatePreviewSpans();
		} );

		function highlightSinglePreviewWord( idx ) {
			var prev = previewBox.querySelector( '.tts-preview-word.tts-active' );
			if ( prev ) prev.classList.remove( 'tts-active' );

			if ( idx >= 0 ) {
				var target = previewBox.querySelector( '.tts-preview-word[data-word-idx="' + idx + '"]' );
				if ( target ) target.classList.add( 'tts-active' );
			}
		}

		function startStudioWordSync( audio ) {
			stopStudioWordSync();

			function tick() {
				if ( ! state.playing || ! state.audio ) return;

				var cMap = chunkWordMaps[ state.chunkIndex ];
				if ( cMap && cMap.words.length && audio.duration ) {
					var progress = Math.max( 0, Math.min( 0.999, audio.currentTime / audio.duration ) );
					var targetOffset = progress * cMap.totalChars;
					var acc = 0;
					var matched = cMap.start;

					for ( var i = 0; i < cMap.words.length; i++ ) {
						acc += cMap.words[i].len;
						if ( targetOffset <= acc || i === cMap.words.length - 1 ) {
							matched = cMap.words[i].idx;
							break;
						}
					}
					highlightSinglePreviewWord( matched );
				}
				animFrameId = requestAnimationFrame( tick );
			}

			animFrameId = requestAnimationFrame( tick );
		}

		function stopStudioWordSync() {
			if ( animFrameId ) {
				cancelAnimationFrame( animFrameId );
				animFrameId = null;
			}
		}

		function resetHighlight() {
			stopStudioWordSync();
			highlightSinglePreviewWord( -1 );
		}

		function setPlayingState( isPlaying ) {
			state.playing = isPlaying;
			if ( isPlaying ) {
				playBtn.querySelector( '.tts-btn-text' ).textContent = config.i18n.btnPause;
				playBtn.querySelector( '.dashicons' ).className = 'dashicons dashicons-controls-pause';
				stopBtn.style.display = 'inline-flex';
				equalizer.classList.add( 'is-active' );
			} else {
				playBtn.querySelector( '.tts-btn-text' ).textContent = config.i18n.btnPlay;
				playBtn.querySelector( '.dashicons' ).className = 'dashicons dashicons-controls-play';
				if ( ! state.paused ) stopBtn.style.display = 'none';
				equalizer.classList.remove( 'is-active' );
			}
		}

		function stopStudioPlayback() {
			stopStudioWordSync();
			if ( state.audio ) {
				state.audio.pause();
				state.audio.src = '';
				state.audio = null;
			}
			if ( 'speechSynthesis' in window ) {
				window.speechSynthesis.cancel();
			}
			state.playing = false;
			state.paused  = false;
			state.chunkIndex = 0;
			setPlayingState( false );
			resetHighlight();
			statusText.textContent = config.i18n.stopped;
		}

		function playStudioChunk() {
			if ( state.chunkIndex >= state.chunks.length ) {
				stopStudioPlayback();
				statusText.textContent = 'Đã phát xong toàn bộ bài đọc';
				return;
			}

			var chunkText = state.chunks[ state.chunkIndex ];
			statusText.textContent = 'Đang đọc câu ' + ( state.chunkIndex + 1 ) + ' / ' + state.chunks.length;

			var selectedVoice = voiceSelect ? voiceSelect.value : 'female_standard';
			var rate = speedSelect ? parseFloat( speedSelect.value ) : 1;

			// Tinh chỉnh tốc độ theo từng phong cách giọng đọc
			var actualRate = rate;
			if ( selectedVoice === 'male_warm' ) {
				actualRate = rate * 0.88; // Nam trầm ấm
			} else if ( selectedVoice === 'female_south' ) {
				actualRate = rate * 1.08; // Nữ Nam Bộ
			} else if ( selectedVoice === 'central' ) {
				actualRate = rate * 0.96; // Miền Trung
			}

			// Phát qua Google TTS proxy streaming
			var proxy = config.proxyUrl || '';
			var url;
			if ( proxy ) {
				url = proxy + '?action=tts_reader_proxy&l=vi&v=' + encodeURIComponent( selectedVoice ) + '&t=' + encodeURIComponent( chunkText );
			} else {
				url = 'https://translate.google.com/translate_tts?ie=UTF-8&tl=vi&client=tw-ob&q=' + encodeURIComponent( chunkText );
			}

			var audio = new Audio( url );
			audio.playbackRate = actualRate;
			state.audio = audio;

			audio.onplay = function () {
				startStudioWordSync( audio );
			};

			audio.onended = function () {
				if ( ! state.playing ) return;
				state.chunkIndex++;
				playStudioChunk();
			};

			audio.onerror = function () {
				if ( ! state.playing ) return;
				state.chunkIndex++;
				playStudioChunk();
			};

			audio.play().catch( function () {} );
		}

		playBtn.addEventListener( 'click', function () {
			if ( state.playing ) {
				// Tạm dừng
				if ( state.audio ) state.audio.pause();
				if ( 'speechSynthesis' in window ) window.speechSynthesis.pause();
				state.paused = true;
				setPlayingState( false );
				statusText.textContent = config.i18n.paused;
				return;
			}

			if ( state.paused ) {
				// Tiếp tục
				if ( state.audio ) state.audio.play();
				if ( 'speechSynthesis' in window ) window.speechSynthesis.resume();
				state.paused = false;
				setPlayingState( true );
				statusText.textContent = config.i18n.playing;
				return;
			}

			// Bắt đầu mới
			updatePreviewSpans();
			if ( ! state.chunks.length ) return;

			state.chunkIndex = 0;
			setPlayingState( true );
			playStudioChunk();
		} );

		stopBtn.addEventListener( 'click', stopStudioPlayback );
	}

	/* ==================================================================
	 * 2. SHORTCODE GENERATOR
	 * ================================================================== */
	function initShortcodeGenerator() {
		var voiceSel = document.getElementById( 'tts-sc-voice' );
		var rateSel  = document.getElementById( 'tts-sc-rate' );
		var textInp  = document.getElementById( 'tts-sc-text' );
		var codeEl   = document.getElementById( 'tts-generated-shortcode' );
		var copyBtn  = document.getElementById( 'tts-copy-shortcode-btn' );
		var toast    = document.getElementById( 'tts-admin-toast' );

		if ( ! voiceSel || ! rateSel || ! textInp || ! codeEl ) return;

		function regenerate() {
			var voice = voiceSel.value;
			var rate  = rateSel.value;
			var text  = textInp.value.trim() || 'Nội dung cần đọc to...';

			var code = '[tts_reader voice="' + voice + '" rate="' + rate + '"]' + text + '[/tts_reader]';
			codeEl.textContent = code;
		}

		voiceSel.addEventListener( 'change', regenerate );
		rateSel.addEventListener( 'change', regenerate );
		textInp.addEventListener( 'input', regenerate );

		if ( copyBtn ) {
			copyBtn.addEventListener( 'click', function () {
				var code = codeEl.textContent;
				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( code ).then( showToast ).catch( fallbackCopy );
				} else {
					fallbackCopy();
				}
			} );
		}

		function fallbackCopy() {
			var ta = document.createElement( 'textarea' );
			ta.value = codeEl.textContent;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild( ta );
			ta.focus();
			ta.select();
			try {
				document.execCommand( 'copy' );
				showToast();
			} catch ( e ) {
				alert( config.i18n.copyFail );
			}
			document.body.removeChild( ta );
		}

		function showToast() {
			if ( ! toast ) return;
			toast.querySelector( '.tts-toast-text' ).textContent = config.i18n.copied;
			toast.classList.add( 'is-show' );
			setTimeout( function () {
				toast.classList.remove( 'is-show' );
			}, 2500 );
		}
	}

	/* ==================================================================
	 * 3. BROWSER VOICE SCANNER
	 * ================================================================== */
	function initBrowserVoiceScanner() {
		var scanBtn     = document.getElementById( 'tts-scan-browser-voices' );
		var resultList  = document.getElementById( 'tts-browser-voices-list' );
		var studioGroup = document.getElementById( 'tts-studio-system-voices' );

		if ( ! scanBtn || ! resultList ) return;

		if ( ! ( 'speechSynthesis' in window ) ) {
			scanBtn.disabled = true;
			resultList.innerHTML = '<p class="tts-muted">Trình duyệt này không hỗ trợ Web Speech API.</p>';
			return;
		}

		var synth = window.speechSynthesis;

		function getVietVoices() {
			return synth.getVoices().filter( function ( v ) {
				return v.lang && v.lang.toLowerCase().indexOf( 'vi' ) === 0;
			} );
		}

		function renderVoices() {
			var voices = getVietVoices();
			if ( ! voices.length ) {
				resultList.innerHTML = '<div class="tts-muted" style="margin-top:10px;">' + config.i18n.noVoice + '</div>';
				return;
			}

			var html = '<div style="margin-top:10px;font-weight:600;margin-bottom:6px;">' + config.i18n.foundVoice + '</div>';
			voices.forEach( function ( v ) {
				var isGoogle = /google/i.test( v.name );
				var isNeural = /neural/i.test( v.name );
				var badge = isNeural
					? '<span class="tts-badge tts-badge-success">Microsoft Neural</span>'
					: ( isGoogle ? '<span class="tts-badge tts-badge-success">Google Natural</span>' : '<span class="tts-badge">Hệ thống</span>' );

				html += '<div class="tts-voice-item">'
					+ '<span><strong>' + escapeHtml( v.name ) + '</strong> <code style="font-size:11px;">(' + escapeHtml( v.lang ) + ')</code></span>'
					+ badge
					+ '</div>';
			} );
			resultList.innerHTML = html;

			// Bổ sung vào Studio dropdown nếu có giọng mới
			if ( studioGroup ) {
				studioGroup.innerHTML = '';
				voices.forEach( function ( v ) {
					var opt = document.createElement( 'option' );
					opt.value = 'system:' + v.name;
					opt.textContent = '💻 ' + v.name + ' (' + v.lang + ')';
					studioGroup.appendChild( opt );
				} );
			}
		}

		scanBtn.addEventListener( 'click', function () {
			resultList.innerHTML = '<p class="tts-muted">' + config.i18n.checking + '</p>';
			var voices = synth.getVoices();
			if ( voices && voices.length ) {
				renderVoices();
			} else {
				synth.onvoiceschanged = function () {
					renderVoices();
				};
				setTimeout( renderVoices, 1200 );
			}
		} );
	}

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

} )();
