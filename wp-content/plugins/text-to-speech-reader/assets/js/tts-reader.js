/**
 * Text to Speech Reader AI Pro — Front-end Logic v3.2
 *
 * Tính năng chính:
 *   1. Highlight 1 chữ duy nhất theo thời gian thực (Single-Word-by-Word Highlighting).
 *   2. Đọc bài viết tiếng Việt chuẩn tự nhiên qua Google TTS streaming proxy.
 *   3. Tải file âm thanh MP3 hoàn chỉnh nghe offline.
 *   4. Điều khiển: Play / Pause / Resume / Stop / Speed.
 */
( function () {
	'use strict';

	var cfg = window.ttsReaderConfig || {
		lang:            'vi-VN',
		rate:            1,
		engine:          'google',
		enableHighlight: true,
		highlightColor:  'yellow',
		autoScroll:      true,
		enableDownload:  true,
		proxyUrl:        '',
		i18n: {
			play:            'Nghe bài viết',
			pause:           'Tạm dừng',
			resume:          'Tiếp tục',
			stop:            'Dừng',
			loading:         'Đang tải...',
			downloading:     'Đang chuẩn bị MP3...',
			downloadReady:   'Đã tải xong!',
			downloadError:   'Lỗi tải MP3. Thử lại sau.',
			unsupported:     'Trình duyệt không hỗ trợ đọc văn bản.',
			error:           'Không thể tải giọng đọc. Vui lòng kiểm tra kết nối mạng.',
		},
	};

	var state = {
		playing:            false,
		paused:             false,
		currentWrapper:     null,
		currentRate:        cfg.rate || 1,

		// Audio Chunks & Playback
		chunks:             [],
		chunkIndex:         0,
		audioEl:            null,
		nextAudioEl:        null,

		// Highlight 1 chữ duy nhất (Single-Word Highlight)
		allWords:           [], // Danh sách { idx, text, el, len }
		chunkWordMaps:      [], // Mảng { startIdx, endIdx, totalChars, words } cho mỗi chunk
		activeWordEl:       null,
		activeWordIdx:      -1,
		animFrameId:        null,
		highlightContainer: null,
		originalHtml:       null,
	};

	/* ==================================================================
	 * 1. TÁCH CÂU & TRÍCH XUẤT NỘI DUNG
	 * ================================================================== */

	function splitText( text, maxLen ) {
		maxLen = maxLen || 140;
		var results = [];
		var lines = text.replace( /\r\n/g, '\n' ).split( /\n+/ );

		lines.forEach( function ( line ) {
			line = line.trim();
			if ( ! line ) return;

			if ( line.length <= maxLen ) {
				results.push( line );
				return;
			}

			var segments = line.split( /(?<=[.!?;,。！？；，—])\s+/ );
			var buf = '';

			segments.forEach( function ( seg ) {
				if ( ! seg.trim() ) return;
				if ( buf && ( buf.length + seg.length + 1 > maxLen ) ) {
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

		return results.filter( function ( s ) { return s.trim().length > 0; } );
	}

	function extractCleanText( el ) {
		var clone = el.cloneNode( true );
		var toRemove = clone.querySelectorAll( '.tts-reader-wrapper, script, style, noscript, svg, iframe' );
		toRemove.forEach( function ( n ) { n.parentNode.removeChild( n ); } );
		return clone.textContent.replace( /\s+/g, ' ' ).trim();
	}

	function googleTtsUrl( text, lang ) {
		var langCode = ( lang || 'vi-VN' ).split( '-' )[0];
		var base = cfg.proxyUrl || '';

		if ( ! base ) {
			return 'https://translate.google.com/translate_tts'
				+ '?ie=UTF-8&tl=' + encodeURIComponent( langCode )
				+ '&client=tw-ob&q=' + encodeURIComponent( text );
		}

		return base
			+ '?action=tts_reader_proxy'
			+ '&l=' + encodeURIComponent( langCode )
			+ '&t=' + encodeURIComponent( text );
	}

	/* ==================================================================
	 * 2. TÔ SÁNG 1 CHỮ DUY NHẤT ĐANG ĐỌC (SINGLE-WORD HIGHLIGHT)
	 * ================================================================== */

	function prepareWordHighlights( container, chunks ) {
		if ( ! cfg.enableHighlight || ! container ) return;

		// Lưu trữ HTML gốc trước khi can thiệp
		if ( state.originalHtml === null ) {
			state.originalHtml = container.innerHTML;
			state.highlightContainer = container;
		}

		// Gán class màu highlight theo cài đặt
		var colorTheme = cfg.highlightColor || 'yellow';
		container.classList.remove( 'tts-highlight-theme-yellow', 'tts-highlight-theme-emerald', 'tts-highlight-theme-blue', 'tts-highlight-theme-orange' );
		container.classList.add( 'tts-highlight-theme-' + colorTheme );

		var walker = document.createTreeWalker(
			container,
			NodeFilter.SHOW_TEXT,
			{
				acceptNode: function ( node ) {
					if ( ! node.nodeValue || ! node.nodeValue.trim() ) return NodeFilter.FILTER_REJECT;
					var parent = node.parentNode;
					if ( ! parent ) return NodeFilter.FILTER_REJECT;
					var tag = parent.tagName.toUpperCase();
					if ( tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT' || tag === 'MARK' ) {
						return NodeFilter.FILTER_REJECT;
					}
					return NodeFilter.FILTER_ACCEPT;
				}
			},
			false
		);

		var textNodes = [];
		var n;
		while ( ( n = walker.nextNode() ) ) {
			textNodes.push( n );
		}

		var allWords = [];
		var globalIdx = 0;

		textNodes.forEach( function ( node ) {
			var text = node.nodeValue;
			var tokens = text.split( /(\s+)/ );
			if ( ! tokens.length ) return;

			var frag = document.createDocumentFragment();

			tokens.forEach( function ( token ) {
				if ( ! token ) return;
				if ( /^\s+$/.test( token ) ) {
					frag.appendChild( document.createTextNode( token ) );
				} else {
					var span = document.createElement( 'span' );
					span.className = 'tts-word';
					span.setAttribute( 'data-word-idx', globalIdx );
					span.textContent = token;
					frag.appendChild( span );

					allWords.push( {
						idx: globalIdx,
						text: token,
						len: Math.max( 1, token.length ),
						el: span
					} );
					globalIdx++;
				}
			} );

			node.parentNode.replaceChild( frag, node );
		} );

		state.allWords = allWords;
		mapChunksToWords( chunks, allWords );
	}

	function mapChunksToWords( chunks, allWords ) {
		state.chunkWordMaps = [];
		if ( ! chunks.length || ! allWords.length ) return;

		var currentWordCursor = 0;

		chunks.forEach( function ( chunkText ) {
			var chunkWords = chunkText.trim().split( /\s+/ ).filter( function ( w ) { return w.length > 0; } );
			var count = chunkWords.length;
			if ( count === 0 ) count = 1;

			var startIdx = currentWordCursor;
			var endIdx = Math.min( allWords.length - 1, startIdx + count - 1 );
			currentWordCursor = endIdx + 1;

			var totalChars = 0;
			var wordsInChunk = [];
			for ( var i = startIdx; i <= endIdx; i++ ) {
				if ( allWords[i] ) {
					totalChars += allWords[i].len;
					wordsInChunk.push( allWords[i] );
				}
			}
			if ( totalChars === 0 ) totalChars = 1;

			state.chunkWordMaps.push( {
				startIdx:   startIdx,
				endIdx:     endIdx,
				totalChars: totalChars,
				words:      wordsInChunk
			} );
		} );
	}

	function highlightSingleWord( wordIdx ) {
		if ( ! cfg.enableHighlight ) return;
		if ( state.activeWordIdx === wordIdx ) return;

		// Bỏ highlight chữ cũ trước đó
		if ( state.activeWordEl ) {
			state.activeWordEl.classList.remove( 'tts-word-active' );
			state.activeWordEl = null;
		}

		if ( wordIdx < 0 || ! state.allWords.length || wordIdx >= state.allWords.length ) {
			state.activeWordIdx = -1;
			return;
		}

		var targetWord = state.allWords[ wordIdx ];
		if ( targetWord && targetWord.el ) {
			targetWord.el.classList.add( 'tts-word-active' );
			state.activeWordEl  = targetWord.el;
			state.activeWordIdx = wordIdx;

			// Tự động cuộn theo từ đang đọc nếu ra ngoài màn hình
			if ( cfg.autoScroll ) {
				var rect = targetWord.el.getBoundingClientRect();
				var windowHeight = window.innerHeight || document.documentElement.clientHeight;
				if ( rect.top < 80 || rect.bottom > windowHeight - 80 ) {
					targetWord.el.scrollIntoView( {
						behavior: 'smooth',
						block: 'center',
						inline: 'nearest'
					} );
				}
			}
		}
	}

	function startWordSyncLoop() {
		stopWordSyncLoop();

		function tick() {
			if ( ! state.playing || ! state.audioEl ) return;

			var audio = state.audioEl;
			var chunkMap = state.chunkWordMaps[ state.chunkIndex ];

			if ( chunkMap && chunkMap.words.length ) {
				var duration = audio.duration;
				var currentTime = audio.currentTime;

				if ( duration && ! isNaN( duration ) && duration > 0 ) {
					var progress = Math.max( 0, Math.min( 0.999, currentTime / duration ) );
					var targetCharOffset = progress * chunkMap.totalChars;

					var accumulated = 0;
					var matchedWordIdx = chunkMap.startIdx;

					for ( var i = 0; i < chunkMap.words.length; i++ ) {
						accumulated += chunkMap.words[i].len;
						if ( targetCharOffset <= accumulated || i === chunkMap.words.length - 1 ) {
							matchedWordIdx = chunkMap.words[i].idx;
							break;
						}
					}

					highlightSingleWord( matchedWordIdx );
				}
			}

			state.animFrameId = requestAnimationFrame( tick );
		}

		state.animFrameId = requestAnimationFrame( tick );
	}

	function stopWordSyncLoop() {
		if ( state.animFrameId ) {
			cancelAnimationFrame( state.animFrameId );
			state.animFrameId = null;
		}
	}

	function restoreOriginalDom() {
		stopWordSyncLoop();
		highlightSingleWord( -1 );

		if ( state.highlightContainer && state.originalHtml !== null ) {
			state.highlightContainer.innerHTML = state.originalHtml;
			state.originalHtml       = null;
			state.highlightContainer = null;
			state.allWords           = [];
			state.chunkWordMaps      = [];
		}
	}

	/* ==================================================================
	 * 3. HỆ THỐNG PHÁT ÂM THANH
	 * ================================================================== */

	function makeAudio( text ) {
		var url   = googleTtsUrl( text, cfg.lang );
		var audio = new Audio( url );
		audio.preload = 'auto';
		audio.playbackRate = state.currentRate || 1;
		return audio;
	}

	function playCurrentChunk( wrapper ) {
		if ( state.chunkIndex >= state.chunks.length ) {
			stopAll( wrapper );
			return;
		}

		var audio;
		if ( state.nextAudioEl ) {
			audio = state.nextAudioEl;
			state.nextAudioEl = null;
		} else {
			audio = makeAudio( state.chunks[ state.chunkIndex ] );
		}

		state.audioEl = audio;
		audio.playbackRate = state.currentRate || 1;

		audio.onplay = function () {
			startWordSyncLoop();
		};

		audio.onended = function () {
			if ( ! state.playing ) return;
			state.chunkIndex++;
			playCurrentChunk( wrapper );
		};

		audio.onerror = function () {
			if ( ! state.playing ) return;
			state.chunkIndex++;
			playCurrentChunk( wrapper );
		};

		audio.play().catch( function () {} );

		// Pre-buffer câu kế tiếp
		var nextIdx = state.chunkIndex + 1;
		if ( nextIdx < state.chunks.length ) {
			state.nextAudioEl = makeAudio( state.chunks[ nextIdx ] );
		}
	}

	function startReading( wrapper, text ) {
		stopAll( wrapper );

		state.chunks     = splitText( text );
		state.chunkIndex = 0;
		state.playing    = true;
		state.paused     = false;
		state.currentWrapper = wrapper;
		state.currentRate    = getSelectedRate( wrapper );

		var targetSelector = wrapper.getAttribute( 'data-tts-target' );
		var targetEl = targetSelector ? document.querySelector( targetSelector ) : null;

		if ( targetEl ) {
			prepareWordHighlights( targetEl, state.chunks );
		}

		setWrapperState( wrapper, 'playing' );
		playCurrentChunk( wrapper );
	}

	/* ==================================================================
	 * 4. TẢI FILE MP3 OFFLINE
	 * ================================================================== */

	function downloadArticleMp3( wrapper ) {
		var downloadBtn = wrapper.querySelector( '.tts-reader-download' );
		if ( ! downloadBtn || downloadBtn.classList.contains( 'is-loading' ) ) return;

		var targetSelector = wrapper.getAttribute( 'data-tts-target' );
		var targetEl = targetSelector ? document.querySelector( targetSelector ) : null;
		if ( ! targetEl ) return;

		var text = extractCleanText( targetEl );
		if ( ! text ) return;

		var chunks = splitText( text );
		if ( ! chunks.length ) return;

		var labelEl   = downloadBtn.querySelector( '.tts-download-label' );
		var spinnerEl = downloadBtn.querySelector( '.tts-download-spinner' );
		var iconEl    = downloadBtn.querySelector( '.tts-download-icon' );

		downloadBtn.classList.add( 'is-loading' );
		if ( spinnerEl ) spinnerEl.style.display = 'inline-block';
		if ( iconEl ) iconEl.style.display = 'none';

		var buffers = [];
		var completed = 0;

		function fetchChunk( index ) {
			if ( index >= chunks.length ) {
				var blob = new Blob( buffers, { type: 'audio/mpeg' } );
				var url  = URL.createObjectURL( blob );

				var a = document.createElement( 'a' );
				a.href = url;

				var rawTitle = wrapper.getAttribute( 'data-tts-title' ) || document.title || 'bai-viet';
				var safeTitle = rawTitle.replace( /[^a-zA-Z0-9\u00C0-\u1EF9\s\-_]/g, '' ).trim().replace( /\s+/g, '-' );
				a.download = ( safeTitle || 'audio-bai-viet' ) + '.mp3';

				document.body.appendChild( a );
				a.click();
				document.body.removeChild( a );

				setTimeout( function () { URL.revokeObjectURL( url ); }, 1000 );

				if ( labelEl ) labelEl.textContent = cfg.i18n.downloadReady;
				downloadBtn.classList.remove( 'is-loading' );
				downloadBtn.classList.add( 'is-success' );
				if ( spinnerEl ) spinnerEl.style.display = 'none';
				if ( iconEl ) iconEl.style.display = 'inline-flex';

				setTimeout( function () {
					downloadBtn.classList.remove( 'is-success' );
					if ( labelEl ) labelEl.textContent = 'Tải MP3';
				}, 3000 );
				return;
			}

			var percent = Math.round( ( completed / chunks.length ) * 100 );
			if ( labelEl ) labelEl.textContent = 'Tải MP3 (' + percent + '%)';

			var chunkUrl = googleTtsUrl( chunks[ index ], cfg.lang );

			fetch( chunkUrl )
				.then( function ( res ) {
					if ( ! res.ok ) throw new Error( 'HTTP ' + res.status );
					return res.arrayBuffer();
				} )
				.then( function ( buffer ) {
					buffers.push( buffer );
					completed++;
					fetchChunk( index + 1 );
				} )
				.catch( function () {
					completed++;
					fetchChunk( index + 1 );
				} );
		}

		fetchChunk( 0 );
	}

	/* ==================================================================
	 * 5. ĐIỀU PHỐI PLAYER & EVENTS
	 * ================================================================== */

	function stopAll( wrapper ) {
		stopWordSyncLoop();

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

		restoreOriginalDom();

		if ( wrapper ) setWrapperState( wrapper, 'idle' );
		state.currentWrapper = null;
	}

	function togglePlayPause( wrapper ) {
		if ( state.currentWrapper && state.currentWrapper !== wrapper ) {
			stopAll( state.currentWrapper );
		}

		// Đang tạm dừng -> Tiếp tục
		if ( state.currentWrapper === wrapper && state.paused ) {
			if ( state.audioEl ) {
				state.audioEl.play().catch( function () {} );
			}
			state.paused  = false;
			state.playing = true;
			setWrapperState( wrapper, 'playing' );
			startWordSyncLoop();
			return;
		}

		// Đang phát -> Tạm dừng
		if ( state.currentWrapper === wrapper && state.playing ) {
			if ( state.audioEl ) state.audioEl.pause();
			state.paused  = true;
			state.playing = false;
			setWrapperState( wrapper, 'paused' );
			stopWordSyncLoop();
			return;
		}

		// Bắt đầu đọc bài viết mới
		var targetSelector = wrapper.getAttribute( 'data-tts-target' );
		var targetEl = targetSelector ? document.querySelector( targetSelector ) : null;
		if ( ! targetEl ) return;

		var text = extractCleanText( targetEl );
		if ( ! text ) return;

		startReading( wrapper, text );
	}

	function getSelectedRate( wrapper ) {
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

	/* ==================================================================
	 * 6. KHỞI TẠO DOM READY
	 * ================================================================== */
	document.addEventListener( 'DOMContentLoaded', function () {
		var wrappers = document.querySelectorAll( '.tts-reader-wrapper' );

		wrappers.forEach( function ( wrapper ) {
			var playBtn     = wrapper.querySelector( '.tts-reader-play' );
			var stopBtn     = wrapper.querySelector( '.tts-reader-stop' );
			var speedSel    = wrapper.querySelector( '.tts-reader-speed' );
			var downloadBtn = wrapper.querySelector( '.tts-reader-download' );

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

			if ( speedSel ) {
				speedSel.addEventListener( 'change', function () {
					state.currentRate = parseFloat( speedSel.value );
					if ( state.audioEl ) {
						state.audioEl.playbackRate = state.currentRate;
					}
				} );
			}

			if ( downloadBtn ) {
				downloadBtn.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					downloadArticleMp3( wrapper );
				} );
			}
		} );

		window.addEventListener( 'beforeunload', function () {
			stopAll( state.currentWrapper );
		} );
	} );

} )();
