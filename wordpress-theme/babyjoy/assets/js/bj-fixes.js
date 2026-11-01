/* Our fixes on top of the client's concept (the concept's own files stay untouched).
   Tabs: the icons in each product tab use the fade-up-on-scroll effect (WOW.js), which only checks
   the screen when the page scrolls, so a newly opened tab stayed empty until the page moved.
   Right after a tab is chosen, ask the effect to check again so the icons appear at once. */
(function () {
  "use strict";
  function reveal() { window.dispatchEvent(new Event("scroll")); }
  document.addEventListener("click", function (e) {
    if (!e.target.closest(".wp-tabs button, .rv-tabs button, [role='tab']")) return;
    requestAnimationFrame(reveal);
    setTimeout(reveal, 120);
  });
})();
