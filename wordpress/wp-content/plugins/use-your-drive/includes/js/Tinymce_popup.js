jQuery(document).ready(function ($) {
  'use strict';

  /* Tabs*/
  $('ul.useyourdrive-nav-tabs li:not(".disabled")').click(function () {
    if ($(this).hasClass('disabled')) {
      return false;
    }
    var tab_id = $(this).attr('data-tab');

    $('ul.useyourdrive-nav-tabs  li').removeClass('current');
    $('.useyourdrive-tab-panel').removeClass('current');

    $(this).addClass('current');
    $("#" + tab_id).addClass('current');

    var hash = location.hash.replace('#', '');
    location.hash = tab_id;
    window.scrollTo(0, 0);
    window.parent.scroll(0, 0);
  });

  if (location.hash && location.hash.indexOf('TB_inline') < 0) {
    jQuery("ul.useyourdrive-nav-tabs " + location.hash + "_tab").trigger('click');
  }

  /* Fix for not scrolling popup*/
  if (/Android|webOS|iPhone|iPod|iPad|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
    var parent = $(tinyMCEPopup.getWin().document);

    if (parent.find('#safari_fix').length === 0) {
      parent.find('.mceWrapper iframe').wrap(function () {
        return $('<div id="safari_fix"/>').css({
          'width': "100%",
          'height': "100%",
          'overflow': 'auto',
          '-webkit-overflow-scrolling': 'touch'
        });
      });
    }
  }

  /* Set mode */
  var mode = $('body').attr('data-mode');

  $("input[name=mode]:radio").change(function () {

    $('.forfilebrowser, .forgallery, .foraudio, .forvideo, .forsearch').hide();
    $("#UseyourDrive_linkedfolders").trigger('change');

    $('#settings_upload_tab, #settings_advanced_tab, #settings_manipulation_tab, #settings_notifications_tab, #settings_mediafiles_tab, #settings_layout_tab, #settings_sorting_tab, #settings_exclusions_tab').removeClass('disabled');
    $('.download-options').show();

    mode = $(this).val();
    switch (mode) {
      case 'files':
        $('.forfilebrowser').not('.hidden').show();
        $('#settings_mediafiles_tab').addClass('disabled');
        break;

      case'upload':
        $('.foruploadbox').not('.hidden').show();
        $('#settings_upload_tab, #settings_notifications_tab').removeClass('disabled');
        $('#settings_mediafiles_tab, #settings_sorting_tab, #settings_advanced_tab, #settings_exclusions_tab, #settings_manipulation_tab').addClass('disabled');
        $('.download-options').hide();
        $('#UseyourDrive_upload').prop("checked", true).trigger('change');
        $('#UseyourDrive_notificationdownload, #UseyourDrive_notificationdeletion').closest('.option').hide();
        break;

      case 'search':
        $('.forsearch').not('.hidden').show();
        $('#settings_mediafiles_tab').addClass('disabled');
        $('#settings_upload_tab').addClass('disabled');
        $('#UseyourDrive_search_field').prop("checked", true).trigger('change');
        break;

      case 'gallery':
        $('.forgallery').show();
        $('#settings_mediafiles_tab').addClass('disabled');
        break;

      case 'audio':
        $('.foraudio').show();
        $('.root-folder').show();
        $('#settings_mediafiles_tab').removeClass('disabled');
        $('#settings_upload_tab, #settings_advanced_tab, #settings_manipulation_tab, #settings_notifications_tab').addClass('disabled');
        break;

      case 'video':
        $('.forvideo').show();
        $('.root-folder').show();
        $('#settings_mediafiles_tab').removeClass('disabled');
        $('#settings_upload_tab, #settings_advanced_tab, #settings_manipulation_tab, #settings_notifications_tab').addClass('disabled');
        break;
    }

    $("#UseyourDrive_breadcrumb, #UseyourDrive_showcolumnnames, #UseyourDrive_mediapurchase, #UseyourDrive_search, #UseyourDrive_showfiles, #UseyourDrive_slideshow, #UseyourDrive_upload, #UseyourDrive_encryption, #UseyourDrive_upload_convert, #UseyourDrive_rename, #UseyourDrive_move, #UseyourDrive_editdescription, #UseyourDrive_delete, #UseyourDrive_addfolder").trigger('change');
    $('input[name=UseyourDrive_file_layout]:radio:checked').trigger('change').prop('checked', true);
    $('#UseyourDrive_linkedfolders').trigger('change');
  });

  $("input[name=UseyourDrive_file_layout]:radio").change(function () {
    switch ($(this).val()) {
      case 'grid':
        $('.forlistonly').fadeOut();
        break;
      case 'list':
        $('.forlistonly').fadeIn();
        break;
    }
  });

  $('[data-div-toggle]').change(function () {
    var toggleelement = '.' + $(this).attr('data-div-toggle');

    if ($(this).is(":checkbox")) {
      if ($(this).is(":checked")) {
        $(toggleelement).fadeIn().removeClass('hidden');
      } else {
        $(toggleelement).fadeOut().addClass('hidden');
      }
    } else if ($(this).is("select")) {
      if ($(this).val() === $(this).attr('data-div-toggle-value')) {
        $(toggleelement).fadeIn().removeClass('hidden');
      } else {
        $(toggleelement).fadeOut().addClass('hidden');
      }
    }
  });

  $("#UseyourDrive_linkedfolders").change(function () {
    $('input[name=UseyourDrive_userfolders_method]:radio:checked').trigger('change').prop('checked', true);
  });

  $("input[name=UseyourDrive_userfolders_method]:radio").change(function () {
    var is_checked = $("#UseyourDrive_linkedfolders").is(":checked");

    $('.root-folder').show();
    switch ($(this).val()) {
      case 'manual':
        if (is_checked) {
          $('.root-folder').hide();
        }
        $('.option-userfolders_auto').hide().addClass('hidden');
        break;
      case 'auto':
        $('.root-folder').show();
        $('.option-userfolders_auto').show().removeClass('hidden');
        break;
    }
  });


  $("input[name=sort_field]:radio").change(function () {
    switch ($(this).val()) {
      case 'shuffle':
        $('.option-sort-field').hide();
        break;
      default:
        $('.option-sort-field').show();
        break;
    }
  });

  $('.useyourdrive .get_shortcode').click(showRawShortcode);
  $(".useyourdrive  .insert_links").click(createDirectLinks);
  $(".useyourdrive .insert_embedded").click(insertEmbedded);
  $('.useyourdrive  .insert_shortcode').click(function (event) {
    insertUseyourDriveShortCode(event)
  });
  $('.useyourdrive  .insert_shortcode_gf').click(function (event) {
    insertUseyourDriveShortCodeGF(event)
  });

  $('.useyourdrive  .insert_shortcode_cf').click(function (event) {
    insertUseyourDriveShortCodeCF(event)
  });

  $('.useyourdrive  .insert_shortcode_woocommerce').click(function (event) {
    insertUseyourDriveShortCodeWC(event)
  });

  $('.useyourdrive .list-container').on('click', '.entry_media_shortcode', function (event) {
    insertUseyourDriveShortCodeMedia($(this).closest('.entry'));
  });

  $(".UseyourDrive img.preloading").unveil(200, $(".UseyourDrive .ajax-filelist"), function () {
    $(this).load(function () {
      $(this).removeClass('preloading');
    });
  });

  /* Initialise from shortcode */
  $('input[name=mode]:radio:checked').trigger('change').prop('checked', true);

  function createShortcode() {
    var dir = $(".root-folder .UseyourDrive.files").attr('data-id'),
            custom_class = $('#UseyourDrive_class').val(),
            linkedfolders = $('#UseyourDrive_linkedfolders').prop("checked"),
            show_files = $('#UseyourDrive_showfiles').prop("checked"),
            max_files = $('#UseyourDrive_maxfiles').val(),
            show_folders = $('#UseyourDrive_showfolders').prop("checked"),
            show_filesize = $('#UseyourDrive_filesize').prop("checked"),
            show_filedate = $('#UseyourDrive_filedate').prop("checked"),
            filelayout = $("input[name=UseyourDrive_file_layout]:radio:checked").val(),
            show_ext = $('#UseyourDrive_showext').prop("checked"),
            show_columnnames = $('#UseyourDrive_showcolumnnames').prop("checked"),
            candownloadzip = $('#UseyourDrive_candownloadzip').prop("checked"),
            canpopout = $('#UseyourDrive_canpopout').prop("checked"),
            showsharelink = $('#UseyourDrive_showsharelink').prop("checked"),
            showrefreshbutton = $('#UseyourDrive_showrefreshbutton').prop("checked"),
            show_breadcrumb = $('#UseyourDrive_breadcrumb').prop("checked"),
            breadcrumb_roottext = $('#UseyourDrive_roottext').val(),
            search = $('#UseyourDrive_search').prop("checked"),
            search_field = $('#UseyourDrive_search_field').prop("checked"),
            search_from = $('#UseyourDrive_searchfrom').prop("checked"),
            previewinline = $('#UseyourDrive_previewinline').prop("checked"),
            allow_preview = $('#UseyourDrive_allow_preview').prop("checked"),
            include_ext = $('#UseyourDrive_include_ext').val(),
            include = $('#UseyourDrive_include').val(),
            exclude_ext = $('#UseyourDrive_exclude_ext').val(),
            exclude = $('#UseyourDrive_exclude').val(),
            sort_field = $("input[name=sort_field]:radio:checked").val(),
            sort_order = $("input[name=sort_order]:radio:checked").val(),
            slideshow = $('#UseyourDrive_slideshow').prop("checked"),
            pausetime = $('#UseyourDrive_pausetime').val(),
            maximages = $('#UseyourDrive_maximage').val(),
            show_filenames = $('#UseyourDrive_showfilenames').prop("checked"),
            target_height = $('#UseyourDrive_targetHeight').val(),
            max_width = $('#UseyourDrive_max_width').val(),
            max_height = $('#UseyourDrive_max_height').val(),
            upload = $('#UseyourDrive_upload').prop("checked"),
            upload_folder = $('#UseyourDrive_upload_folder').prop("checked"),
            upload_ext = $('#UseyourDrive_upload_ext').val(),
            encryption = $('#UseyourDrive_encryption').prop("checked"),
            encryption_passphrase = $('#UseyourDrive_encryption_passphrase').val(),
            maxfilesize = $('#UseyourDrive_maxfilesize').val(),
            maxnumberofuploads = $('#UseyourDrive_maxnumberofuploads').val(),
            convert = $('#UseyourDrive_upload_convert').prop("checked"),
            convert_formats = readCheckBoxes("input[name='UseyourDrive_upload_convert_formats[]']"),
            overwrite = $('#UseyourDrive_overwrite').prop("checked"),
            rename = $('#UseyourDrive_rename').prop("checked"),
            move = $('#UseyourDrive_move').prop("checked"),
            editdescription = $('#UseyourDrive_editdescription').prop("checked"),
            can_delete = $('#UseyourDrive_delete').prop("checked"),
            can_addfolder = $('#UseyourDrive_addfolder').prop("checked"),
            deletetotrash = $('#UseyourDrive_deletetotrash').prop("checked"),
            notification_download = $('#UseyourDrive_notificationdownload').prop("checked"),
            notification_upload = $('#UseyourDrive_notificationupload').prop("checked"),
            notification_deletion = $('#UseyourDrive_notificationdeletion').prop("checked"),
            notification_emailaddress = $('#UseyourDrive_notification_email').val(),
            notification_skip_email_currentuser = $('#UseyourDrive_notification_skip_email_currentuser').prop("checked"),
            user_folders = $('#UseyourDrive_user_folders').prop("checked"),
            use_template_dir = $('#UseyourDrive_userfolders_template').prop("checked"),
            template_dir = $(".template-folder .UseyourDrive.files").attr('data-id'),
            view_role = readCheckBoxes("input[name='UseyourDrive_view_role[]']"),
            download_role = readCheckBoxes("input[name='UseyourDrive_download_role[]']"),
            share_role = readCheckBoxes("input[name='UseyourDrive_share_role[]']"),
            upload_role = readCheckBoxes("input[name='UseyourDrive_upload_role[]']"),
            rename_files_role = readCheckBoxes("input[name='UseyourDrive_rename_files_role[]']"),
            rename_folders_role = readCheckBoxes("input[name='UseyourDrive_rename_folders_role[]']"),
            move_files_role = readCheckBoxes("input[name='UseyourDrive_move_files_role[]']"),
            move_folders_role = readCheckBoxes("input[name='UseyourDrive_move_folders_role[]']"),
            editdescription_role = readCheckBoxes("input[name='UseyourDrive_editdescription_role[]']"),
            delete_files_role = readCheckBoxes("input[name='UseyourDrive_delete_files_role[]']"),
            delete_folders_role = readCheckBoxes("input[name='UseyourDrive_delete_folders_role[]']"),
            addfolder_role = readCheckBoxes("input[name='UseyourDrive_addfolder_role[]']"),
            view_user_folders_role = readCheckBoxes("input[name='UseyourDrive_view_user_folders_role[]']"),
            mediaextensions = readCheckBoxes("input[name='UseyourDrive_mediaextensions[]']"),
            autoplay = $('#UseyourDrive_autoplay').prop("checked"),
            showplaylist = $('#UseyourDrive_showplaylist').prop("checked"),
            covers = $('#UseyourDrive_covers').prop("checked"),
            linktomedia = $('#UseyourDrive_linktomedia').prop("checked"),
            mediapurchase = $('#UseyourDrive_mediapurchase').prop("checked"),
            linktoshop = $('#UseyourDrive_linktoshop').val();

    var data = '';

    if (UseyourDrive_vars.shortcodeRaw === '1') {
      data += '[raw]';
    }

    data += '[useyourdrive ';

    if (custom_class !== '') {
      data += 'class="' + custom_class + '" ';
    }

    if (typeof dir === 'undefined' && ($("input[name=UseyourDrive_userfolders_method]:radio:checked").val() !== 'manual')) {
      $('#settings_folder_tab a').trigger('click');
      return false;
    }

    if (dir !== '') {
      if (linkedfolders) {
        if ($("input[name=UseyourDrive_userfolders_method]:radio:checked").val() !== 'manual') {
          data += 'dir="' + dir + '" ';
        }
      } else {
        data += 'dir="' + dir + '" ';
      }
    }

    if (max_width !== '') {
      if (max_width.indexOf("px") !== -1 || max_width.indexOf("%") !== -1) {
        data += 'maxwidth="' + max_width + '" ';
      } else {
        data += 'maxwidth="' + parseInt(max_width) + '" ';
      }
    }

    if (max_height !== '') {
      if (max_height.indexOf("px") !== -1 || max_height.indexOf("%") !== -1) {
        data += 'maxheight="' + max_height + '" ';
      } else {
        data += 'maxheight="' + parseInt(max_height) + '" ';
      }
    }

    data += 'mode="' + $("input[name=mode]:radio:checked").val() + '" ';

    if (include_ext !== '') {
      data += 'includeext="' + include_ext + '" ';
    }

    if (include !== '') {
      data += 'include="' + include + '" ';
    }

    if (exclude_ext !== '') {
      data += 'excludeext="' + exclude_ext + '" ';
    }

    if (exclude !== '') {
      data += 'exclude="' + exclude + '" ';
    }

    if (view_role !== 'administrator|editor|author|contributor|subscriber|pending|guest') {
      data += 'viewrole="' + view_role + '" ';
    }

    if (sort_field !== 'name') {
      data += 'sortfield="' + sort_field + '" ';
    }

    if (sort_field !== 'shuffle' && sort_order !== 'asc') {
      data += 'sortorder="' + sort_order + '" ';
    }

    if (linkedfolders === true) {
      var method = $("input[name=UseyourDrive_userfolders_method]:radio:checked").val();
      data += 'userfolders="' + method + '" ';

      if (method === 'auto' && use_template_dir === true && template_dir !== '') {
        data += 'usertemplatedir="' + template_dir + '" ';
      }

      if (view_user_folders_role !== 'administrator') {
        data += 'viewuserfoldersrole="' + view_user_folders_role + '" ';
      }
    }

    if (mode === 'upload') {
      data += 'downloadrole="none" ';
    } else if (download_role !== 'administrator|editor|author|contributor|subscriber|pending|guest') {
      data += 'downloadrole="' + download_role + '" ';
    }

    var mode = $("input[name=mode]:radio:checked").val();
    switch (mode) {
      case 'audio':
      case 'video':

        if (mediaextensions === 'none') {
          $('#settings_mediafiles_tab a').trigger('click');
          $('[name="UseyourDrive_mediaextensions[]"]').next().css("color", "red");
          return false;
        }
        data += 'mediaextensions="' + mediaextensions + '" ';

        if (autoplay === true) {
          data += 'autoplay="1" ';
        }

        if (showplaylist === false) {
          data += 'hideplaylist="1" ';
        }

        if (covers === true) {
          data += 'covers="1" ';
        }

        if (linktomedia === true) {
          data += 'linktomedia="1" ';
        }

        if (mediapurchase === true && linktoshop !== '') {
          data += 'linktoshop="' + linktoshop + '" ';
        }

        break;

      case 'files':
      case 'gallery':
      case 'upload':
      case 'search':
        if (mode === 'gallery') {


          if (maximages !== '') {
            data += 'maximages="' + maximages + '" ';
          }

          if (show_filenames === true) {
            data += 'showfilenames="1" ';
          }

          if (target_height !== '') {
            data += 'targetheight="' + target_height + '" ';
          }

          if (slideshow === true) {
            data += 'slideshow="1" ';
            if (pausetime !== '') {
              data += 'pausetime="' + pausetime + '" ';
            }
          }
        }

        if (mode === 'files' || mode === 'search') {

          if (show_filesize === false) {
            data += 'filesize="0" ';
          }

          if (show_filedate === false) {
            data += 'filedate="0" ';
          }

          if (filelayout === 'list') {
            data += 'filelayout="list" ';
          }

          if (show_ext === false) {
            data += 'showext="0" ';
          }

          if (allow_preview === false) {
            data += 'forcedownload="1" ';
          }

          if (canpopout === true) {
            data += 'canpopout="1" ';
          }

          if (show_columnnames === false) {
            data += 'showcolumnnames="0" ';
          }
        }

        if (show_files === false) {
          data += 'showfiles="0" ';
        }
        if (show_folders === false) {
          data += 'showfolders="0" ';
        }

        if (max_files !== '-1' && max_files !== '') {
          data += 'maxfiles="' + max_files + '" ';
        }

        if (maxnumberofuploads !== '-1' && maxnumberofuploads !== '0' && maxnumberofuploads !== '') {
          data += 'maxnumberofuploads="' + maxnumberofuploads + '" ';
        }

        if (previewinline === false) {
          data += 'previewinline="0" ';
        }
        if (candownloadzip === true) {
          data += 'candownloadzip="1" ';
        }

        if (showsharelink === true) {
          data += 'showsharelink="1" ';

          if (share_role !== 'all') {
            data += 'sharerole="' + share_role + '" ';
          }

        }

        if (showrefreshbutton === false) {
          data += 'showrefreshbutton="0" ';
        }

        if (search === false && mode !== 'search') {
          data += 'search="0" ';
        } else {
          if (search_field === true) {
            data += 'searchcontents="1" ';
          }

          if (search_from === true) {
            data += 'searchfrom="selectedroot" ';
          }
        }

        if (show_breadcrumb === true) {
          if (breadcrumb_roottext !== '') {
            data += 'roottext="' + breadcrumb_roottext + '" ';
          }
        } else {
          data += 'showbreadcrumb="0" ';
        }

        if (notification_download === true || notification_upload === true || notification_deletion === true) {
          if (notification_emailaddress !== '') {
            data += 'notificationemail="' + notification_emailaddress + '" ';
          }

          if (notification_skip_email_currentuser === true) {
            data += 'notification_skipemailcurrentuser="1" ';
          }

        }

        if (notification_download === true) {
          data += 'notificationdownload="1" ';
        }

        if (upload === true) {
          data += 'upload="1" ';

          if (upload_folder === false) {
            data += 'upload_folder="0" ';
          }

          if (upload_role !== 'administrator|editor|author|contributor|subscriber') {
            data += 'uploadrole="' + upload_role + '" ';
          }
          if (maxfilesize !== '') {
            data += 'maxfilesize="' + maxfilesize + '" ';
          }
          if (convert === true) {
            data += 'convert="1" ';

            if ($("input[name='UseyourDrive_upload_convert_formats[]']").not(":checked").length > 0) {
              data += 'convertformats="' + convert_formats + '" ';
            }
          }
          if (encryption === true) {
            data += 'upload_encryption="1" upload_encryption_passphrase="' + encryption_passphrase + '" ';
          }

          if (upload_ext !== '') {
            data += 'uploadext="' + upload_ext + '" ';
          }

          if (overwrite === true) {
            data += 'overwrite="1" ';
          }

          if (notification_upload === true) {
            data += 'notificationupload="1" ';
          }

        }

        if (rename === true) {
          data += 'rename="1" ';

          if (rename_files_role !== 'administrator|editor') {
            data += 'renamefilesrole="' + rename_files_role + '" ';
          }
          if (rename_folders_role !== 'administrator|editor') {
            data += 'renamefoldersrole="' + rename_folders_role + '" ';
          }
        }

        if (move === true) {
          data += 'move="1" ';

          if (move_files_role !== 'administrator|editor') {
            data += 'movefilesrole="' + move_files_role + '" ';
          }
          if (move_folders_role !== 'administrator|editor') {
            data += 'movefoldersrole="' + move_folders_role + '" ';
          }
        }

        if (editdescription === true) {
          data += 'editdescription="1" ';

          if (editdescription_role !== 'administrator|editor') {
            data += 'editdescriptionrole="' + editdescription_role + '" ';
          }
        }

        if (can_delete === true) {
          data += 'delete="1" ';

          if (delete_files_role !== 'administrator|editor') {
            data += 'deletefilesrole="' + delete_files_role + '" ';
          }

          if (delete_folders_role !== 'administrator|editor') {
            data += 'deletefoldersrole="' + delete_folders_role + '" ';
          }

          if (notification_deletion === true) {
            data += 'notificationdeletion="1" ';
          }

          if (deletetotrash === false) {
            data += 'deletetotrash="0" ';
          }
        }

        if (can_addfolder === true) {
          data += 'addfolder="1" ';

          if (addfolder_role !== 'administrator|editor') {
            data += 'addfolderrole="' + addfolder_role + '" ';
          }
        }

        break;
    }

    data += ']';

    if (UseyourDrive_vars.shortcodeRaw === '1') {
      data += '[/raw]';
    }

    return data;
  }

  function insertUseyourDriveShortCode(event) {
    var data = createShortcode();
    event.preventDefault();

    if (data !== false) {
      tinyMCEPopup.execCommand('mceInsertContent', false, data);
      // Refocus in window
      if (tinyMCEPopup.isWindow)
        window.focus();
      tinyMCEPopup.editor.focus();
      tinyMCEPopup.close();
    }
  }

  function insertUseyourDriveShortCodeGF(event) {
    event.preventDefault();

    var data = createShortcode();
    if (data !== false) {
      $('#field_useyourdrive', window.parent.document).val(data);
      window.parent.SetFieldProperty('UseyourdriveShortcode', data);
      window.parent.tb_remove();
    }
  }

  function insertUseyourDriveShortCodeCF(event) {
    event.preventDefault();

    var data = createShortcode();
    if (data !== false) {
      var encoded_data = window.btoa(unescape(encodeURIComponent(data)));

      $('.useyourdrive-shortcode-value', window.parent.document).val(encoded_data);
      window.parent.jQuery('.useyourdrive-shortcode-value').trigger('change');

      if (data.indexOf('userfolders="auto"') > -1) {
        $('.use-your-drive-upload-folder', window.parent.document).fadeIn();
      } else {
        $('.use-your-drive-upload-folder', window.parent.document).fadeOut();
      }

      window.parent.window.modal_action.close();
    }
  }

  function insertUseyourDriveShortCodeWC(event) {
    event.preventDefault();

    var data = createShortcode();
    if (data !== false) {
      $('#useyourdrive_upload_box_shortcode', window.parent.document).val(data);
      window.parent.tb_remove();
    }
  }


  function insertUseyourDriveShortCodeMedia($entry_element) {

    $("#UseyourDrive_showplaylist").prop("checked", false);
    $("#UseyourDrive_include").val($entry_element.data('id'));
    var file_name = $entry_element.find('.entry_link:first').data('filename');

    $.each($('.foraudio input[name="UseyourDrive_mediaextensions[]"]'), function () {
      var extension = '.' + $(this).val();

      if (file_name.indexOf(extension) > -1) {
        $(this).prop("checked", true);
        $("input#audio").trigger('click');
        return false;
      }
    });

    $.each($('.forvideo input[name="UseyourDrive_mediaextensions[]"]'), function () {
      var extension = '.' + $(this).val();

      if (file_name.indexOf(extension) > -1) {
        $(this).prop("checked", true);
        $("input#video").trigger('click');
        return false;
      }
    });

    var data = createShortcode();

    if (data !== false) {
      tinyMCEPopup.execCommand('mceInsertContent', false, data);
      // Refocus in window
      if (tinyMCEPopup.isWindow)
        window.focus();
      tinyMCEPopup.editor.focus();
      tinyMCEPopup.close();
    }
  }

  function showRawShortcode() {
    /* Close any open modal windows */
    $('#useyourdrive-modal-action').remove();
    var shortcode = createShortcode();

    if (shortcode === false) {
      return false;
    }


    /* Build the Shortcode Dialog */
    var modalbuttons = '';
    modalbuttons += '<button class="simple-button blue useyourdrive-modal-copy-btn" type="button" title="' + UseyourDrive_vars.str_copy_to_clipboard_title + '" >' + UseyourDrive_vars.str_copy_to_clipboard_title + '</button>';
    var modalheader = $('<a tabindex="0" class="close-button" title="' + UseyourDrive_vars.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
    var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" ><strong>' + shortcode + '</strong></div>');
    var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
    var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal"><div class="modal-dialog"><div class="modal-content"></div></div></div>');
    $('body').append(modaldialog);
    $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

    /* Set the button actions */
    $('#useyourdrive-modal-action .useyourdrive-modal-copy-btn').unbind('click');
    $('#useyourdrive-modal-action .useyourdrive-modal-copy-btn').click(function () {

      var $temp = $("<input>");
      $("body").append($temp);
      $temp.val(shortcode).select();
      document.execCommand("copy");
      $temp.remove();

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

    return false;
  }

  function createDirectLinks() {
    var listtoken = $(".UseyourDrive.files").attr('data-token'),
            lastpath = $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-path'),
            entries = readGDriveArrCheckBoxes(".UseyourDrive[data-token='" + listtoken + "'] input[name='selected-files[]']");

    if (entries.length === 0) {
      if (tinyMCEPopup.isWindow)
        window.focus();
      tinyMCEPopup.editor.focus();
      tinyMCEPopup.close();
    }

    $.ajax({
      type: "POST",
      url: UseyourDrive_vars.ajax_url,
      data: {
        action: 'useyourdrive-create-link',
        listtoken: listtoken,
        lastpath: lastpath,
        entries: entries,
        _ajax_nonce: UseyourDrive_vars.createlink_nonce
      },
      beforeSend: function () {
        $(".UseyourDrive .loading").height($(".UseyourDrive .ajax-filelist").height());
        $(".UseyourDrive .loading").fadeTo(400, 0.8);
        $(".UseyourDrive .insert_links").attr('disabled', 'disabled');
      },
      complete: function () {
        $(".UseyourDrive .loading").fadeOut(400);
        $(".UseyourDrive .insert_links").removeAttr('disabled');
      },
      success: function (response) {
        if (response !== null) {
          if (response.links !== null && response.links.length > 0) {

            var data = '';

            $.each(response.links, function (key, linkresult) {
              data += '<a class="UseyourDrive-directlink" href="' + linkresult.link + '" target="_blank">' + linkresult.name + '</a><br/>';
            });

            tinyMCEPopup.execCommand('mceInsertContent', false, data);
            // Refocus in window
            if (tinyMCEPopup.isWindow)
              window.focus();
            tinyMCEPopup.editor.focus();
            tinyMCEPopup.close();
          } else {
          }
        }
      },
      dataType: 'json'
    });
    return false;
  }

  function insertEmbedded() {
    var listtoken = $(".UseyourDrive.files").attr('data-token'),
            lastpath = $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-path'),
            entries = readGDriveArrCheckBoxes(".UseyourDrive[data-token='" + listtoken + "'] input[name='selected-files[]']");

    if (entries.length === 0) {
      if (tinyMCEPopup.isWindow)
        window.focus();
      tinyMCEPopup.editor.focus();
      tinyMCEPopup.close();
    }

    $.ajax({
      type: "POST",
      url: UseyourDrive_vars.ajax_url,
      data: {
        action: 'useyourdrive-embedded',
        listtoken: listtoken,
        lastpath: lastpath,
        entries: entries,
        _ajax_nonce: UseyourDrive_vars.createlink_nonce
      },
      beforeSend: function () {
        $(".UseyourDrive .loading").height($(".UseyourDrive .ajax-filelist").height());
        $(".UseyourDrive .loading").fadeTo(400, 0.8);
        $(".UseyourDrive .insert_links").attr('disabled', 'disabled');
      },
      complete: function () {
        $(".UseyourDrive .loading").fadeOut(400);
        $(".UseyourDrive .insert_links").removeAttr('disabled');
      },
      success: function (response) {
        if (response !== null) {
          if (response.links !== null && response.links.length > 0) {

            var data = '';

            $.each(response.links, function (key, linkresult) {
              if (linkresult.type === 'iframe') {
                data += '<iframe src="' + linkresult.embeddedlink + '" height="480" style="width:100%;" frameborder="0" scrolling="no" class="uyd-embedded" allowfullscreen></iframe>';
              } else if (linkresult.type === 'image') {
                data += '<img src="' + linkresult.embeddedlink + '"\>';
              }
            });

            tinyMCEPopup.execCommand('mceInsertContent', false, data);
            // Refocus in window
            if (tinyMCEPopup.isWindow)
              window.focus();
            tinyMCEPopup.editor.focus();
            tinyMCEPopup.close();
          } else {
          }
        }
      },
      dataType: 'json'
    });
    return false;
  }

  function readCheckBoxes(element) {
    var values = readGDriveArrCheckBoxes(element);

    if (values.length === 0) {
      return "none";
    }

    if (values.length === $(element).length) {
      return "all";
    }

    return values.join('|');
  }

  function readGDriveArrCheckBoxes(element) {
    var values = $(element + ":checked").map(function () {
      return this.value;
    }).get();
    return values;
  }
});