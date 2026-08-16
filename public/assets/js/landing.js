(function () {
  'use strict';

  var nav = document.querySelector('.lp-nav');
  var toggle = document.getElementById('lpNavToggle');
  var links = document.getElementById('lpNavLinks');

  if (nav) {
    window.addEventListener('scroll', function () {
      nav.classList.toggle('is-scrolled', window.scrollY > 24);
    }, { passive: true });
  }

  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.innerHTML = open ? '<i class="bi bi-x-lg"></i>' : '<i class="bi bi-list"></i>';
    });
    links.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
          links.classList.remove('is-open');
          toggle.setAttribute('aria-expanded', 'false');
          toggle.innerHTML = '<i class="bi bi-list"></i>';
        }
      });
    });
  }

  var reveals = document.querySelectorAll('.reveal');
  if (reveals.length && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('is-visible');
          io.unobserve(e.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-visible'); });
  }

  var slider = document.querySelector('[data-slider]');
  if (slider) {
    var slides = Array.prototype.slice.call(slider.querySelectorAll('.lp-gallery__slide'));
    var dots = Array.prototype.slice.call(slider.querySelectorAll('[data-slider-goto]'));
    var prevBtn = slider.querySelector('[data-slider-prev]');
    var nextBtn = slider.querySelector('[data-slider-next]');
    var captionEl = slider.querySelector('[data-slider-caption]');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var current = 0;
    var timer = null;

    function show(i) {
      current = (i + slides.length) % slides.length;
      slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === current); });
      dots.forEach(function (d, idx) { d.classList.toggle('is-active', idx === current); });
      if (captionEl && dots[current]) captionEl.textContent = dots[current].dataset.sliderCaptionText || '';
    }

    function start() {
      if (reduceMotion || slides.length < 2) return;
      stop();
      timer = setInterval(function () { show(current + 1); }, 5000);
    }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    if (prevBtn) prevBtn.addEventListener('click', function () { show(current - 1); start(); });
    if (nextBtn) nextBtn.addEventListener('click', function () { show(current + 1); start(); });
    dots.forEach(function (d) {
      d.addEventListener('click', function () { show(parseInt(d.dataset.sliderGoto, 10)); start(); });
    });
    slider.addEventListener('mouseenter', stop);
    slider.addEventListener('mouseleave', start);
    slider.addEventListener('focusin', stop);
    slider.addEventListener('focusout', start);

    start();
  }
})();
