$(function () {
  // Tooltips: rank icons etc. (.tooltip) and admin hints ([data-hint]) use their title text.
  $(".tooltip, [data-hint]").tooltipster({ theme: "tooltipster-utstats", delay: 100 });

  // Click rows (left button only)
  $(".clickableRow").on("click", function (event) {
    if (event.which === 1) {
      window.location = $(this).attr("href");
    }
  });

  // Credits changelog toggle
  $(".changelog").on("click", function () {
    $("#contentChangeLog").slideToggle(300);
  });
});
