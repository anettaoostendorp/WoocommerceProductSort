jQuery(function ($) {
  const selector = $('#wcpsp-selector');

  if (!selector.length) {
    return;
  }

  selector.on('change', function () {
    const presetId = $(this).val();

    if (!presetId) {
      return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('wcpsp_preset', presetId);
    window.location.href = url.toString();
  });
});
