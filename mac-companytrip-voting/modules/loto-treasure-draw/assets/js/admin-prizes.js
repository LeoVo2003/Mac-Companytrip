(function ($) {
  "use strict";
  $(document).on('click', '.ltr-choose-image', function (e) {
    e.preventDefault();
    var btn = $(this);
    var frame = wp.media({
      title: 'Chọn hình ảnh phần quà',
      multiple: false,
      library: { type: 'image' }
    });
    frame.on('select', function () {
      var att = frame.state().get('selection').first().toJSON();
      var wrap = btn.closest('form');
      wrap.find('.ltr-image-id').val(att.id);
      wrap.find('.ltr-image-preview').attr('src', att.url).show();
    });
    frame.open();
  });
})(jQuery);
