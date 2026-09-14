$(document).ready(function () {
  var $switcher = $('.megamenu-switcher');

  if (!$switcher.length) {
    return;
  }

  $switcher.on('click', '.nav-link', function () {
    var $button = $(this);
    var idCategory = $button.data('id-category');

    $switcher.find('.nav-link').removeClass('active');
    $button.addClass('active');

    $.post($switcher.data('select-url'), {
      id_category: idCategory,
    });
  });
});
