(() => {
  'use strict';

  const background = document.querySelector('[data-scroll-video-background]');
  const video = background?.querySelector('video');
  if (!background || !video) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const seekThreshold = 1 / 120;
  let duration = 0;
  let maximumScroll = 0;
  let targetTime = 0;
  let displayedTime = 0;
  let animationFrame = 0;
  let measurementFrame = 0;
  let failed = false;

  const measure = () => {
    measurementFrame = 0;
    maximumScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    updateTarget();
  };

  const scheduleMeasure = () => {
    if (!measurementFrame) measurementFrame = requestAnimationFrame(measure);
  };

  const seek = time => {
    const safeTime = clamp(time, 0, duration);
    if (Math.abs(video.currentTime - safeTime) >= seekThreshold) video.currentTime = safeTime;
    displayedTime = safeTime;
  };

  const animate = () => {
    animationFrame = 0;
    if (failed || reducedMotion.matches || !duration || document.hidden) return;

    const difference = targetTime - displayedTime;
    if (Math.abs(difference) <= seekThreshold) {
      seek(targetTime);
      return;
    }

    const catchUp = Math.abs(difference) > duration * 0.16 ? 0.58 : 0.38;
    seek(displayedTime + difference * catchUp);
    animationFrame = requestAnimationFrame(animate);
  };

  const requestUpdate = () => {
    if (!animationFrame && !failed && !reducedMotion.matches) animationFrame = requestAnimationFrame(animate);
  };

  function updateTarget() {
    if (!duration || failed || reducedMotion.matches) return;
    const progress = maximumScroll > 0 ? clamp(window.scrollY / maximumScroll, 0, 1) : 0;
    targetTime = progress * duration;
    background.style.setProperty('--scroll-video-shade-opacity', (1 - progress * 0.2).toFixed(3));
    requestUpdate();
  }

  const syncMetadata = () => {
    if (!Number.isFinite(video.duration) || video.duration <= 0) return;
    duration = video.duration;
    displayedTime = clamp(video.currentTime || 0, 0, duration);
    measure();
  };

  const handleReducedMotion = () => {
    if (animationFrame) cancelAnimationFrame(animationFrame);
    animationFrame = 0;
    if (reducedMotion.matches && duration) seek(0);
    else measure();
  };

  video.addEventListener('loadedmetadata', syncMetadata);
  video.addEventListener('durationchange', syncMetadata);
  video.addEventListener('loadeddata', updateTarget);
  video.addEventListener('canplay', updateTarget);
  video.addEventListener('seeking', () => { displayedTime = video.currentTime; });
  video.addEventListener('seeked', () => { displayedTime = video.currentTime; });
  video.addEventListener('error', () => {
    failed = true;
    background.classList.add('video-failed');
    console.error('The decorative background video could not be loaded.');
  }, { once: true });

  window.addEventListener('scroll', updateTarget, { passive: true });
  window.addEventListener('resize', scheduleMeasure, { passive: true });
  window.addEventListener('orientationchange', scheduleMeasure, { passive: true });
  window.addEventListener('pageshow', measure);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) measure();
  });
  reducedMotion.addEventListener?.('change', handleReducedMotion);

  if ('ResizeObserver' in window) new ResizeObserver(scheduleMeasure).observe(document.documentElement);
  document.fonts?.ready.then(scheduleMeasure);
  window.addEventListener('load', scheduleMeasure, { once: true });

  video.pause();
  if (video.readyState >= 1) syncMetadata();
  else scheduleMeasure();
})();
