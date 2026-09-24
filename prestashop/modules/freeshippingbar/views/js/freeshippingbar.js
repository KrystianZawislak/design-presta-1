$(document).ready(function () {
  prestashop.on('updateCart', function () {
    var $bar = $('.free-shipping-bar');

    if (!$bar.length) {
      return;
    }

    $.get($bar.data('refresh-url')).then(function (resp) {
      $bar.replaceWith(resp.preview);
    });
  });
});
