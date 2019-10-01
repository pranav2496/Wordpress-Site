jQuery(function ($) {

  if ($('input.useyourdrive_private_folder_name').length > 0) {
    var gf_block_upload = $('<div>').attr('id', 'gf_block_upload').css({position: 'absolute', top: 0, bottom: 0, left: 0, right: 0, 'z-index': 1000, background: 'rgba(255, 255, 255, 0.49)'});
    $('#UseyourDrive').css('position', 'relative').append(gf_block_upload);
  }

  $('input.useyourdrive_private_folder_name').keyup(function () {

    var name = '';
    $('input.useyourdrive_private_folder_name').each(function () {
      name += $(this).val() + '|';
    });
    name = (name.length > 0) ? name.slice(0, -1) : name;

    if (name.length > 2) {
      gf_block_upload.fadeOut();
      document.cookie = 'UyD-CF7-NAME=' + name + '; path=/';
    } else {
      gf_block_upload.fadeIn();
    }

  });

  $('.UseyourDrive').on('useyourdrive-addupload useyourdrive-removeupload', ".fileuploadform", function () {

    if ($(this).closest('.wpcf7-validates-as-required').length === 0) {
      return;
    }

    if ($(this).closest('.UseyourDrive').data('cpUseyourDrive').number_of_uploaded_files.Counter > 0) {
      $(this).closest('.wpcf7').find('.wpcf7-submit').prop("disabled", false).fadeTo(400, 1);
    } else {
      $(this).closest('.wpcf7').find('.wpcf7-submit').prop("disabled", true).fadeTo(400, 0.3);
    }
  });
  $('.UseyourDrive .fileuploadform').trigger('useyourdrive-addupload');


  var prefill = getCookie('UyD-CF7-NAME');
  if (prefill.length > 0) {
    var input = prefill.split('|');

    $(input).each(function (i, value) {
      $('input.useyourdrive_private_folder_name:eq(' + i + ')').val(value);
    });
  }

  $('input.useyourdrive_private_folder_name').trigger('keyup');

  function getCookie(cname) {
    var name = cname + '=';
    var decodedCookie = decodeURIComponent(document.cookie);
    var ca = decodedCookie.split(';');
    for (var i = 0; i < ca.length; i++) {
      var c = ca[i];
      while (c.charAt(0) == ' ') {
        c = c.substring(1);
      }
      if (c.indexOf(name) == 0) {
        return c.substring(name.length, c.length);
      }
    }
    return '';
  }
});

jQuery(document).ready(function ($) {
  'use strict';

  $("body").on("change", ".useyourdrive-shortcode-value", function () {
    var decoded_shortcode = decodeURIComponent(escape(window.atob($(this).val())));
    $('#useyourdrive-shortcode-decoded-value').text(decoded_shortcode).css('display', 'block');
  });

  var default_value = '[useyourdrive class="cf7_upload_box" mode="upload" upload="1" uploadrole="all" viewrole="all" userfolders="auto" viewuserfoldersrole="none"';
  var encoded_data = window.btoa(unescape(encodeURIComponent(default_value)));
  $(".useyourdrive-shortcode-value", "body").val(encoded_data).trigger('change');

  $("body").on("click", ".UseyourDrive-CF-shortcodegenerator", function () {


    if ($('#useyourdrive-modal-action').length > 0) {
      window.modal_action.open();
      return true;
    }

    /* Build the Insert Dialog */
    var modalbuttons = '';
    var modalheader = $('<a tabindex="0" class="close-button" title="" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
    var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" style="display:none"></div>');
    var modalfooter = $('<div class="useyourdrive-modal-footer" style="display:none"><div class="useyourdrive-modal-buttons">' + '' + '</div></div>');
    var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive useyourdrive-modal useyourdrive-modal80 light"><div class="modal-dialog"><div class="modal-content"><div class="loading"><div class="loader-beat"></div></div></div></div></div>');

    $('body').append(modaldialog);

    var $iframe_template = $('#useyourdrive-shortcode-iframe');
    var $iframe = $iframe_template.clone().appendTo(modalbody).show();

    $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

    var shortcode = $('#useyourdrive-shortcode-decoded-value', 'body').text()
    var shortcode_attr = shortcode.replace('</p>', '').replace('<p>', '').replace('[useyourdrive ', '').replace('"]', '');
    var query = encodeURIComponent(shortcode_attr).split('%3D%22').join('=').split('%22%20').join('&');

    $iframe.attr('src', $iframe_template.attr('data-src') + '&' + query);

    $iframe.load(function () {
      $('.useyourdrive-modal-body').fadeIn();
      $('.useyourdrive-modal-footer').fadeIn();
      $('.modal-content .loading:first').fadeOut();
    });

    /* Open the Dialog and load the images inside it */
    var modal_action = new RModal(document.getElementById('useyourdrive-modal-action'), {
      dialogOpenClass: 'animated slideInDown',
      dialogCloseClass: 'animated slideOutUp',
      escapeClose: true
    });
    document.addEventListener('keydown', function (ev) {
      modal_action.keydown(ev);
    }, false);
    modal_action.open();
    window.modal_action = modal_action;

  });
});