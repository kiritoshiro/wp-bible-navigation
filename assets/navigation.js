/* Bible navigation: open the book named in the URL hash; play videos and audio in place. */
(function () {
  'use strict';

  var ID = /^[A-Za-z0-9_-]+$/;

  function openFromHash() {
    var id = '';
    try {
      id = decodeURIComponent(window.location.hash.slice(1));
    } catch (e) {
      return;
    }
    var book = id ? document.getElementById(id) : null;
    if (book && book.tagName === 'DETAILS' && book.closest('.bnav')) {
      book.open = true;
    }
  }

  function player(link) {
    var box = document.createElement('div');
    var video = link.getAttribute('data-video') || '';
    var list = link.getAttribute('data-list') || '';
    var audio = link.parentNode.querySelector('template.bnav-audio');
    box.className = 'bnav-player';
    if ((video && ID.test(video)) || (list && ID.test(list))) {
      var frame = document.createElement('iframe');
      var query = 'autoplay=1&rel=0' + (list && ID.test(list) ? '&list=' + list : '');
      frame.src = 'https://www.youtube-nocookie.com/embed/' + (video && ID.test(video) ? video : 'videoseries') + '?' + query;
      frame.title = link.textContent;
      frame.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      frame.allowFullscreen = true;
      // YouTube refuses to play (error 153) when the embedding page sends no referrer.
      frame.referrerPolicy = 'strict-origin-when-cross-origin';
      box.className += ' is-video';
      box.appendChild(frame);
      return box;
    }
    if (audio && audio.content) {
      // The server rendered (and escaped) the player; a <template> stays inert until cloned.
      box.appendChild(audio.content.cloneNode(true));
      var sound = box.querySelector('audio');
      var played = sound ? sound.play() : null;
      if (played && played.catch) {
        played.catch(function () {});
      }
      return box;
    }
    return null;
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest ? event.target.closest('.bnav-open') : null;
    if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
      return;
    }
    var item = link.parentNode;
    var open = item.querySelector('.bnav-player');
    event.preventDefault();
    if (open) {
      item.removeChild(open);
      link.setAttribute('aria-expanded', 'false');
      return;
    }
    var box = player(link);
    if (box) {
      item.appendChild(box);
      link.setAttribute('aria-expanded', 'true');
    } else {
      window.location.href = link.href;
    }
  });

  window.addEventListener('hashchange', openFromHash);
  openFromHash();
})();
