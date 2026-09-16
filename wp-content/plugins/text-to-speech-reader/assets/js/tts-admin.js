/**
 * Text to Speech Reader — admin dashboard logic v2.0
 * Kiểm tra giọng đọc tiếng Việt + thử nghe Google TTS Online trong trang Tổng quan.
 */
( function () {
	'use strict';

	var config = window.ttsAdminConfig || {
		testText: 'Xin chào, đây là bản kiểm tra giọng đọc tiếng Việt của plugin Text to Speech Reader.',
		proxyUrl: '',
		i18n: {
			checking:    'Đang kiểm tra danh sách giọng đọc...',
			found:       'Đã tìm thấy giọng đọc tiếng Việt:',
			notFound:    'Không tìm thấy giọng đọc tiếng Việt nào trên trình duyệt/thiết bị này.',
			unsupported: 'Trình duyệt này không hỗ trợ Web Speech API.',
			playing:     'Đang phát giọng Google TTS...',
			error:       'Không thể tải giọng đọc. Kiểm tra kết nối mạng.',
		},
	};

	document.addEventListener( 'DOMContentLoaded', function () {
		var checkBtn   = document.getElementById( 'tts-admin-check-voice' );
		var playBtn    = document.getElementById( 'tts-admin-test-play' );
		var playGoogle = document.getElementById( 'tts-admin-test-google' );
		var resultBox  = document.getElementById( 'tts-admin-voice-result' );

		/* ---- Kiểm tra giọng Web Speech API ---- */
		if ( checkBtn && resultBox ) {
			if ( ! ( 'speechSynthesis' in window ) ) {
				resultBox.innerHTML = '<p class="tts-voice-empty">' + config.i18n.unsupported + '</p>';
				if ( playBtn ) playBtn.disabled = true;
				checkBtn.disabled = true;
			} else {
				var synth = window.speechSynthesis;

				function getVietnameseVoices() {
					var voices = synth.getVoices().filter( function ( v ) {
						return v.lang && v.lang.toLowerCase().indexOf( 'vi' ) === 0;
					} );
					voices.sort( function ( a, b ) {
						return ( /google/i.test( a.name ) ? 0 : 1 ) - ( /google/i.test( b.name ) ? 0 : 1 );
					} );
					return voices;
				}

				function waitForVoices( timeoutMs ) {
					return new Promise( function ( resolve ) {
						var existing = synth.getVoices();
						if ( existing && existing.length ) { resolve( existing ); return; }
						var settled = false;
						function finish() {
							if ( settled ) return;
							settled = true;
							if ( typeof synth.removeEventListener === 'function' ) {
								synth.removeEventListener( 'voiceschanged', onChange );
							}
							resolve( synth.getVoices() );
						}
						function onChange() { finish(); }
						if ( typeof synth.addEventListener === 'function' ) {
							synth.addEventListener( 'voiceschanged', onChange );
						} else {
							synth.onvoiceschanged = onChange;
						}
						setTimeout( finish, timeoutMs || 2000 );
					} );
				}

				function renderVoices() {
					var voices = getVietnameseVoices();
					if ( ! voices.length ) {
						resultBox.innerHTML = '<p class="tts-voice-empty">' + config.i18n.notFound + '</p>';
						return;
					}
					var html = '<p><strong>' + config.i18n.found + '</strong></p>';
					voices.forEach( function ( v ) {
						var isGoogle = /google/i.test( v.name );
						var badge    = isGoogle ? ' <span class="tts-badge tts-badge-on">Google</span>' : ' <span class="tts-badge">Hệ thống</span>';
						html += '<div class="tts-voice-item">' + v.name + ' <code>(' + v.lang + ')</code>' + badge + '</div>';
					} );
					resultBox.innerHTML = html;
				}

				checkBtn.addEventListener( 'click', function () {
					resultBox.innerHTML = '<p>' + config.i18n.checking + '</p>';
					waitForVoices().then( renderVoices );
				} );

				/* ---- Nghe thử Web Speech API ---- */
				if ( playBtn ) {
					playBtn.addEventListener( 'click', function () {
						synth.cancel();
						waitForVoices().then( function () {
							var voices   = getVietnameseVoices();
							var utterance = new SpeechSynthesisUtterance( config.testText );
							utterance.lang = 'vi-VN';
							utterance.rate = 1;
							if ( voices.length ) {
								utterance.voice = voices[0];
								utterance.lang  = voices[0].lang;
							}
							synth.speak( utterance );
						} );
					} );
				}
			}
		}

		/* ---- Nghe thử Google TTS Online ---- */
		if ( playGoogle ) {
			var googleAudio = null;

			playGoogle.addEventListener( 'click', function () {
				if ( googleAudio ) {
					googleAudio.pause();
					googleAudio.src = '';
					googleAudio = null;
					playGoogle.textContent = '🔊 Nghe thử Google TTS';
					return;
				}

				var text  = config.testText;
				var proxy = config.proxyUrl;
				var url;

				if ( proxy ) {
					url = proxy + '?action=tts_reader_proxy&l=vi&t=' + encodeURIComponent( text );
				} else {
					url = 'https://translate.google.com/translate_tts?ie=UTF-8&tl=vi&client=tw-ob&q=' + encodeURIComponent( text );
				}

				playGoogle.textContent = '⏸ Dừng';
				googleAudio = new Audio( url );
				googleAudio.playbackRate = 1;
				googleAudio.onended = function () {
					playGoogle.textContent = '🔊 Nghe thử Google TTS';
					googleAudio = null;
				};
				googleAudio.onerror = function () {
					playGoogle.textContent = '🔊 Nghe thử Google TTS';
					googleAudio = null;
					if ( resultBox ) {
						resultBox.innerHTML = '<p class="tts-voice-empty">' + config.i18n.error + '</p>';
					}
				};
				googleAudio.play().catch( function () {
					playGoogle.textContent = '🔊 Nghe thử Google TTS';
					googleAudio = null;
				} );
			} );
		}
	} );
} )();
