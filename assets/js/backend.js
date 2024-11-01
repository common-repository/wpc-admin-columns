(function($) {
  'use strict';

  let wpcac_media = {
    image_frame: null,
    images_frame: null,
    button: null,
    upload_id: null,
    post_id: wp.media.model.settings.post.id,
  };

  let $json_display = null;

  $(function() {
    // ready
    if ($('.wpcac-actions').length && $('.tablenav').length) {
      $('.wpcac-actions').prependTo($('.tablenav'));

      if (wpcac_vars.intro === '' || wpcac_vars.intro === undefined) {
        $('.tablenav.top').
            find('.wpcac-btn').
            attr('data-intro', wpcac_vars.intro_text);

        let intro = introJs();

        intro.setOptions(
            {'showBullets': false, 'doneLabel': wpcac_vars.intro_done}).
            oncomplete(function() {
              $.post(ajaxurl,
                  {action: 'wpcac_intro_done', nonce: wpcac_vars.nonce});
            });

        intro.start();
      }

      if (wpcac_vars.horizontal_scroll === 'yes') {
        $('body').addClass('wpcac-horizontal-scroll');
        $('.wp-list-table').
            wrap('<div class="wpcac-horizontal-scroll-table"></div>');
      }

      $('body').addClass('wpcac-initialized');
    }

    wpcac_sortable();
    wpcac_init_type();
    wpcac_init_data_type();
    wpcac_color_picker();
    //wpcac_meta_fields();
  });

  $(document).on('select2:open', () => {
    document.querySelector('.select2-search__field').focus();
  });

  $(document).on('click touch', '.wpcac-copy', function(e) {
    e.preventDefault();

    let $this = $(this);
    let text = $this.closest('.wpcac-value-wrapper').
        find('.wpcac-value').
        text();

    // copy to clipboard
    navigator.clipboard.writeText(text).then(function() {
      $this.attr('aria-label', wpcac_vars.copied);
    }, function() {
      $this.attr('aria-label', 'Failure to copy!');
    });

    setTimeout(function() {
      $this.attr('aria-label', wpcac_vars.copy);
    }, 3000);
  });

  $(document).on('click touch', '.wpcac-btn', function(e) {
    e.preventDefault();

    let $popup = $('#wpcac-popup-columns');

    $popup.dialog({
      minWidth: 460,
      title: $popup.attr('data-title'),
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').addClass('wpc-dialog-overlay');
        $('.ui-widget-overlay').bind('click', function() {
          $popup.dialog('close');
        });
      },
    });
  });

  $(document).on('click touch', '.wpcac-edit', function(e) {
    e.preventDefault();

    var name = $(this).data('name');
    var key = $(this).data('key');
    var id = $(this).data('id');
    var uid = $(this).data('uid');
    var tid = $(this).data('tid');
    var field = $(this).data('field');
    var type = $(this).data('type');
    var title = $(this).attr('aria-label');

    $('#wpcac-popup-edit').html('').addClass('wpcac-popup-loading');

    $('#wpcac-popup-edit').dialog({
      minWidth: 460,
      title: title,
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').addClass('wpc-dialog-overlay');
        $('.ui-widget-overlay').bind('click', function() {
          $('#wpcac-popup-edit').dialog('close');
        });
      },
      close: function() {
        $json_display = null;
      },
    });

    var data = {
      action: 'wpcac_edit_get',
      name: name,
      key: key,
      id: id,
      uid: uid,
      tid: tid,
      field: field,
      type: type,
      nonce: wpcac_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('#wpcac-popup-edit').html(response);
      wpcac_color_picker();
      wpcac_image_selector();
      wpcac_images_selector();
      wpcac_images_selector_sortable();
      wpcac_images_selector_remove();
      wpcac_json_editor();
      wpcac_init_select2();
      $('#wpcac-popup-edit').removeClass('wpcac-popup-loading');
    });
  });

  $(document).on('click touch', '.wpcac-edit-save', function(e) {
    e.preventDefault();

    var id = $(this).data('id');
    var uid = $(this).data('uid');
    var tid = $(this).data('tid');
    var field = $(this).data('field');
    var type = $(this).data('type');
    var value = $('.wpcac-edit-value').val();

    $('#wpcac-popup-edit').addClass('wpcac-popup-loading');

    var data = {
      action: 'wpcac_edit_save',
      id: id,
      uid: uid,
      tid: tid,
      field: field,
      type: type,
      value: value,
      nonce: wpcac_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      if (response.status) {
        $('#wpcac-popup-edit').removeClass('wpcac-popup-loading');
        $('#wpcac-popup-edit').dialog('close');

        if (parseInt(id) > 0) {
          $('.wpcac-value[data-id="' + id + '"][data-field="' + field + '"]').
              html(response.value);
        }

        if (parseInt(uid) > 0) {
          $('.wpcac-value[data-uid="' + uid + '"][data-field="' + field + '"]').
              html(response.value);
        }

        if (parseInt(tid) > 0) {
          $('.wpcac-value[data-tid="' + tid + '"][data-field="' + field + '"]').
              html(response.value);
        }
      } else {
        $('#wpcac-popup-edit').removeClass('wpcac-popup-loading');
        $('#wpcac-popup-edit').html(response.value);
      }
    });
  });

  $(document).on('click touch', '.wpcac-remove', function(e) {
    e.preventDefault();

    var r = confirm(wpcac_vars.remove_confirm);

    if (r == true) {
      $(this).closest('.wpcac-column').slideUp().remove();
    }
  });

  $(document).on('click touch', '.wpcac-reset-btn', function(e) {
    e.preventDefault();

    var r = confirm(wpcac_vars.reset_confirm);

    if (r == true) {
      var data = {
        action: 'wpcac_reset_columns',
        screen_key: $(this).closest('.wpcac-btns').data('screen_key'),
        nonce: wpcac_vars.nonce,
      };

      $('#wpcac-popup-columns').addClass('wpcac-popup-loading');

      $.post(ajaxurl, data, function(response) {
        $('#wpcac-popup-columns').removeClass('wpcac-popup-loading');
        $('#wpcac-popup-columns').dialog('close');
        location.reload();
      });
    }
  });

  $(document).on('click touch', '.wpcac-product-variations-btn', function(e) {
    e.preventDefault();

    var id = $(this).data('id');
    var title = $(this).data('title');

    $('#wpcac-popup-view').html('').addClass('wpcac-popup-loading');

    $('#wpcac-popup-view').dialog({
      minWidth: 460,
      title: title,
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').addClass('wpc-dialog-overlay');
        $('.ui-widget-overlay').bind('click', function() {
          $('#wpcac-popup-view').dialog('close');
        });
      },
    });

    var data = {
      action: 'wpcac_product_variations', id: id, nonce: wpcac_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('#wpcac-popup-view').html(response);
      $('#wpcac-popup-view').removeClass('wpcac-popup-loading');
    });
  });

  $(document).on('keyup change', '.wpcac_column_name', function(e) {
    $(this).
        closest('.wpcac-column').
        find('.wpcac-column-heading .name').
        text($(this).val());
  });

  $(document).on('change', '.wpcac_column_type', function(e) {
    $(this).
        closest('.wpcac-column').
        find('.wpcac-column-heading .type').
        text($(this).val());
    wpcac_init_type();
  });

  $(document).on('change', '.wpcac_data_type', function(e) {
    wpcac_init_data_type();
  });

  $(document).on('click touch', '.wpcac-enable-btn', function(e) {
    e.preventDefault();

    let column = $(this).closest('.wpcac-column').data('column');

    if ($(this).hasClass('enabled')) {
      // disable it
      $(this).removeClass('enabled button-primary').addClass('disabled');
      $(this).closest('.enable').attr('aria-label', wpcac_vars.disabled);
      $(this).closest('.wpcac-column').removeClass('wpcac-column-enable');
      $(this).closest('.wpcac-column').find('.wpcac_column_enable').val('no');
      $('.wp-list-table .column-' + column).hide();
    } else {
      // enable it
      $(this).removeClass('disabled').addClass('enabled button-primary');
      $(this).closest('.enable').attr('aria-label', wpcac_vars.enabled);
      $(this).closest('.wpcac-column').addClass('wpcac-column-enable');
      $(this).closest('.wpcac-column').find('.wpcac_column_enable').val('yes');
      $('.wp-list-table .column-' + column).show();
    }
  });

  $(document).on('click touch', '.wpcac-save-btn', function(e) {
    var form_data = $('.wpcac-columns').
        find('input, select, button, textarea').
        serialize() || 0;
    var data = {
      action: 'wpcac_save_columns',
      screen_key: $(this).closest('.wpcac-btns').data('screen_key'),
      form_data: form_data,
      nonce: wpcac_vars.nonce,
    };

    $('#wpcac-popup-columns').addClass('wpcac-popup-loading');

    $.post(ajaxurl, data, function(response) {
      $('#wpcac-popup-columns').removeClass('wpcac-popup-loading');
      $('#wpcac-popup-columns').dialog('close');
      location.reload();
    });
  });

  $(document).on('click touch', '.wpcac-add-btn', function(e) {
    var data = {
      action: 'wpcac_add_column',
      screen_key: $(this).closest('.wpcac-btns').data('screen_key'),
      nonce: wpcac_vars.nonce,
    };

    $('#wpcac-popup-columns').addClass('wpcac-popup-loading');

    $.post(ajaxurl, data, function(response) {
      $(response).appendTo('.wpcac-columns');
      wpcac_init_type();
      wpcac_init_data_type();
      wpcac_color_picker();
      //wpcac_meta_fields();
      $('#wpcac-popup-columns').removeClass('wpcac-popup-loading');
    });
  });

  $(document).on('click touch', '.wpcac-column-heading', function(e) {
    if (($(e.target).closest('.wpcac-enable-btn').length === 0)) {
      $(this).closest('.wpcac-column').toggleClass('active');
    }
  });

  $(document).on('click touch keyup', function(e) {
    if ($json_display !== null) {
      try {
        var json = $json_display.get();

        $('#wpcac-json-editor').val(JSON.stringify(json)).trigger('change');
        $('#wpcac-json-error').html('');
        $('.wpcac-edit-save').prop('disabled', false);
      } catch (ex) {
        $('#wpcac-json-error').html('Wrong JSON Format: ' + ex);
        $('.wpcac-edit-save').prop('disabled', true);
      }
    }
  });

  $(document).on('click touch', '.wpcac-image-remove', function(e) {
    e.preventDefault();
    $(this).
        closest('.wpcac-image-selector').
        find('.wpcac-image-id').val('').trigger('change');
    $(this).
        closest('.wpcac-image-selector').
        find('.wpcac-image-preview').html('');
  });

  function wpcac_image_selector() {
    $('.wpcac-image-add').on('click touch', function(e) {
      e.preventDefault();

      var $button = $(this);
      var upload_id = parseInt($button.attr('rel'));

      wpcac_media.button = $button;

      if (upload_id) {
        wpcac_media.upload_id = upload_id;
      } else {
        wpcac_media.upload_id = wpcac_media.post_id;
      }

      if (wpcac_media.image_frame) {
        wpcac_media.image_frame.uploader.uploader.param('post_id',
            wpcac_media.upload_id);
        wpcac_media.image_frame.open();
        return;
      } else {
        wp.media.model.settings.post.id = wpcac_media.upload_id;
      }

      wpcac_media.image_frame = wp.media.frames.wpcac_image_media = wp.media({
        title: wpcac_vars.media_title, button: {
          text: wpcac_vars.media_add,
        }, library: {
          type: 'image',
        }, multiple: false,
      });

      wpcac_media.image_frame.on('select', function() {
        var selection = wpcac_media.image_frame.state().get('selection');
        var $preview = wpcac_media.button.
            closest('.wpcac-image-selector').
            find('.wpcac-image-preview');
        var $image_id = wpcac_media.button.
            closest('.wpcac-image-selector').
            find('.wpcac-image-id');

        selection.map(function(attachment) {
          attachment = attachment.toJSON();

          if (attachment.id) {
            var url = attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            $preview.html('<img src="' + url + '" />');
            $image_id.val(attachment.id).trigger('change');
          }
        });

        wp.media.model.settings.post.id = wpcac_media.post_id;
      });

      wpcac_media.image_frame.open();
    });
  }

  function wpcac_images_selector() {
    $('.wpcac-images-add').on('click touch', function(e) {
      e.preventDefault();

      var $button = $(this);
      var upload_id = parseInt($button.attr('rel'));

      wpcac_media.button = $button;

      if (upload_id) {
        wpcac_media.upload_id = upload_id;
      } else {
        wpcac_media.upload_id = wpcac_media.post_id;
      }

      if (wpcac_media.images_frame) {
        wpcac_media.images_frame.uploader.uploader.param('post_id',
            wpcac_media.upload_id);
        wpcac_media.images_frame.open();
        return;
      } else {
        wp.media.model.settings.post.id = wpcac_media.upload_id;
      }

      wpcac_media.images_frame = wp.media.frames.wpcac_images_media = wp.media({
        title: wpcac_vars.media_title, button: {
          text: wpcac_vars.media_add,
        }, library: {
          type: 'image',
        }, multiple: true,
      });

      wpcac_media.images_frame.on('select', function() {
        var selection = wpcac_media.images_frame.state().get('selection');
        var $images = wpcac_media.button.closest('.wpcac-images-selector').
            find('ul.wpcac-images');

        selection.map(function(attachment) {
          attachment = attachment.toJSON();

          if (attachment.id) {
            var url = attachment.sizes.thumbnail
                ? attachment.sizes.thumbnail.url
                : attachment.url;
            $images.append('<li data-id="' + attachment.id +
                '"><span href="#" class="wpcac-image-thumb"><a class="wpcac-image-remove" href="#"></a><img src="' +
                url + '" /></span></li>');
          }
        });

        wpcac_images_selector_build_value();

        wp.media.model.settings.post.id = wpcac_media.post_id;
      });

      wpcac_media.images_frame.open();
    });
    $;
  }

  function wpcac_images_selector_sortable() {
    $('.wpcac-images-selector').find('ul.wpcac-images').sortable({
      update: function() {
        wpcac_images_selector_build_value();
      }, placeholder: 'sortable-placeholder', cursor: 'move',
    });
  }

  function wpcac_images_selector_remove() {
    $(document).
        on('click touch', '.wpcac-images-selector .wpcac-image-remove',
            function(e) {
              e.preventDefault();

              $(this).closest('li').remove();
              wpcac_images_selector_build_value();
            });
  }

  function wpcac_images_selector_build_value() {
    var value = [];
    var $selector = $('.wpcac-images-selector');

    if ($selector.find('ul.wpcac-images li').length) {
      $.each($selector.find('ul.wpcac-images li'), function() {
        value.push($(this).data('id'));
      });

      $selector.find('input.wpcac-images-ids').val(value);
    } else {
      $selector.find('input.wpcac-images-ids').val('');
    }
  }

  function wpcac_color_picker() {
    $('.wpcac-color-picker').wpColorPicker();
  }

  function wpcac_json_editor() {
    if ($(document).find('#wpcac-json-editor').length &&
        $(document).find('#wpcac-json-display').length) {
      $json_display = new JsonEditor('#wpcac-json-display');

      if ($('#wpcac-json-editor').val() !== '') {
        $json_display.load(wpcac_json_editor_get_json());
      } else {
        $json_display.load({});
      }
    }
  }

  function wpcac_json_editor_get_json() {
    try {
      return JSON.parse($('#wpcac-json-editor').val());
    } catch (ex) {
      $('#wpcac-json-error').html('Wrong JSON Format: ' + ex);
    }
  }

  function wpcac_sortable() {
    $('.wpcac-columns').
        sortable({handle: '.move', placeholder: 'wpcac-column-placeholder'});
  }

  function wpcac_init_type() {
    $('.wpcac_column_type').each(function() {
      var type = $(this).val();

      $(this).closest('.wpcac-column').
          find('.wpcac-hide-if-type-default').hide();
      $(this).closest('.wpcac-column').
          find('.wpcac-show-if-type-' + type).show();
    });
  }

  function wpcac_init_data_type() {
    $('.wpcac_data_type').each(function() {
      var type = $(this).val();

      $(this).closest('.wpcac-column').
          find('.wpcac-hide-if-data-type-default').hide();
      $(this).closest('.wpcac-column').
          find('.wpcac-show-if-data-type-' + type).show();
    });
  }

  function wpcac_init_select2() {
    $('.wpcac-select2').select2({
      ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            term: params.term, action: 'wpcac_search_terms', taxonomy: $(this).
                closest('.wpcac-select2-wrapper').
                data('taxonomy'),
          };
        }, processResults: function(data) {
          var options = [];

          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }

          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1,
    });

    $('.wpcac-select2-tags').select2({
      tags: true, ajax: {
        url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
          return {
            term: params.term, action: 'wpcac_search_tags', taxonomy: $(this).
                closest('.wpcac-select2-wrapper').
                data('taxonomy'),
          };
        }, processResults: function(data) {
          var options = [];

          if (data) {
            $.each(data, function(index, text) {
              options.push({id: text[0], text: text[1]});
            });
          }

          return {
            results: options,
          };
        }, cache: true,
      }, minimumInputLength: 1, createTag: function(params) {
        var term = $.trim(params.term);

        if (term === '') {
          return null;
        }

        return {
          id: term, text: term, newTag: true, // add additional parameters
        };
      }, insertTag: function(data, tag) {
        // Insert the tag at the end of the results
        data.push(tag);
      },
    });
  }

  function wpcac_meta_fields() {
    $('.wpcac_meta_fields').select2({
      dropdownParent: $('#wpcac-popup-columns'),
    });
  }
})(jQuery);
