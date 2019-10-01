(function ($) {
  'use strict';
  $.widget("cp.UseyourDrive", {
    options: {
      listtoken: null,
      searchQuery: null
    },

    _create: function () {
      /* Remove no JS message */
      this.element.removeClass('jsdisabled');
      this.element.show();
      this.options.topContainer = this.element.parent();
      this.options.loadingContainer = this.element.find('.loading');

      /* Set the max width for the element */
      this.element.css('width', '100%');

      /* Set the shortcode ID */
      this.options.listtoken = this.element.attr('data-token');

      /* Local Cache */
      this.cache = {};

      /* Upload values */
      this.uploaded_files = [];
      this.uploaded_files_storage = {};
      this.number_of_uploaded_files = {
        'Max': this.element.find('input[name="maxnumberofuploads"]').val(),
        'Counter': 0
      };

      /* Mobile? */
      if (/Android|webOS|iPhone|iPod|iPad|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
        var userAgent = navigator.userAgent.toLowerCase();
        if ((userAgent.search("android") > -1) && (userAgent.search("mobile") > -1)) {
          this.options.mobile = true;
        } else if ((userAgent.search("android") > -1) && !(userAgent.search("mobile") > -1)) {
          this.options.mobile = false;
        } else {
          this.options.mobile = true;
        }
      }
      /* Check if user is using a mobile device (including tables) detected by WordPress, alters css*/
      if (this.options.is_mobile === '1') {
        this.options.mobile = true;
        $('html').addClass('uyd-mobile');
      }

      /* Ignite! */
      this._initiate();

    },

    _destroy: function () {
      return this._super();
    },

    _setOption: function (key, value) {
      this._super(key, value);
    },

    _initiate: function () {
      var self = this;


      self.options.topContainer.one('inview', function (event, isInView) {

        self._initResizeHandler();
        self._refreshView();
        self._initCache();

        if (self.options.topContainer.hasClass('files') || self.options.topContainer.hasClass('gallery') || self.options.topContainer.hasClass('search')) {
          self._initFilebrowser();
        }

        if (self.element.find('.fileuploadform').length > 0) {
          self._initUploadBox();
        }

        if (self.options.topContainer.hasClass('video') || self.options.topContainer.hasClass('audio')) {
          self._initMediaPlayer();
        }

      });

      if (self.options.topContainer.hasClass('initiate')) {
        self.options.topContainer.trigger('inview');
      }

      window.setTimeout(function () {
        self.initated = true;
      }, 2000);

    },

    _initFilebrowser: function () {
      this.renderNavMenu();

      /* Do first Request*/
      this._getFileList({});
    },

    _initMediaPlayer: function () {
      //initate_use_your_drive_media();
    },

    _initUploadBox: function () {
      var self = this;

      var is_standalone = self.options.topContainer.hasClass('upload');
      var upload_box = self.element.find('.fileuploadform');
      var upload_form = upload_box.closest('form');
      var autoUpload = true;

      /* Drag & Drop functionality for the Upload Box */
      this._initDragDrop();

      /* Remove Folder upload button if isn't supported by browser */
      if (self._helperIsIE() !== false) {
        $('.upload-multiple-files').parent().remove();
      }

      /* Set Cookie for Guest uploads */
      if (is_standalone && document.cookie.indexOf("UYD-ID=") == -1) {
        var date = new Date();
        date.setTime(date.getTime() + (7 * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toUTCString();
        var id = Math.random().toString(36).substr(2, 16);
        document.cookie = "UYD-ID=" + id + expires + "; path=" + self.options.cookie_path + "; domain=" + self.options.cookie_domain + ";";
      }

      /* Disable Auto Upload in case the Upload Box is part of a Gravity Form or Contact Form */
      if (upload_form.length > 0 && upload_form.find('#gravityflow_update_button').length === 0) {
        autoUpload = false;

        /* Do the upload before the form is submitted */
        var $submit_btn = upload_form.find('input[type="submit"], input[type="button"].gform_next_button, button[id^="gform_submit_button"]');

        $submit_btn.on('click', function (e) {
          if (typeof $(this).data('old-value') === 'undefined') {
            $(this).data('old-value', $(this).val());
          }

          if ((upload_box.closest('.gfield_contains_required').length) && (self.number_of_uploaded_files.Counter === 0)) {
            upload_box.css('border-color', 'red');
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          $(this).addClass("firing").prop("disabled", true).fadeTo(400, 0.3);
          upload_box.css('border-color', '');

          if (upload_form.find(".template-upload").length > 0) {
            $('html, body').stop().animate({
              scrollTop: self.element.offset().top - 150
            }, 1000);

            upload_box.trigger('useyourdrive-startupload');

            $(this).val(self.options.str_processing);
            e.preventDefault();
            e.stopPropagation();
            return false;
          }

          $(this).addClass("firing").prop("disabled", false).fadeTo(400, 1);
          $(this).val($(this).data('old-value'));

          if (upload_form.hasClass('wpcf7-form')) {
            return true;
          }

          upload_form.trigger('submit', [true]);
          return true;
        });
      }

      /* If the Upload Box is part of a Gravity Form or Contact Form, render the rows of the already uploaded content */
      if (upload_form.length > 0 && upload_form.find('.fileupload-filelist').val().length > 0) {
        self.uploaded_files_storage = JSON.parse(self.element.find('.fileupload-filelist').val());

        $.each(self.uploaded_files_storage, function (index, file) {
          self._uploadRenderRow(file);
          self._uploadRenderRowOnFinish(file);
          self.number_of_uploaded_files.Counter++;
        });
      }

      self.element.find('.fileupload-list').click(function () {
        self.element.find(".upload-input-button:first").trigger("click");
      });

      /* The following browsers support XHR and(CHUNKED) file uploads, 
       * which allows advanced usage of the file upload
       * 
       * ###Desktop browsers###
       * - Google Chrome
       * - Apple Safari 5.0+ (6.0+)
       * - Mozilla Firefox 4.0+ (4.0+)
       * - Opera 12.0+ (12.0+)
       * Microsoft Internet Explorer 10.0+ (10.0+)
       * 
       * ###Mobile browsers###
       * Apple Safari Mobile on iOS 6.0+  (6.0+)
       * Google Chrome on iOS 6.0+ (6.0+)
       * Google Chrome on Android 4.0+  (4.0+)
       * Default Browser on Android 3.2+  (NOT SUPPORTED)
       * Opera Mobile 12.0+ (NOT SUPPORTED)
       */
      var use_encryption = (self.element.find('input[name="encryption"]').val() === '1');

      var support_xhr = $.support.xhrFileUpload && !use_encryption;
      var support_chunked = support_xhr && !(new RegExp('(Opera Mobi)|(Android)').test(window.navigator.userAgent));

      var multipart_val = (support_xhr) ? false : true;
      var method_val = (support_xhr) ? 'PUT' : 'POST';
      var singlefileuploads_val = true;
      var maxchunksize_val = (support_chunked) ? 5 * 1024 * 1024 : 0;

      /* Update max file upload for direct uploads */
      if (support_xhr && self.element.find('input[name="maxfilesize"]').attr('data-limit') === '0') {
        self.element.find('input[name="maxfilesize"]').val('');
        self.element.find('.fileupload-container').find('.max-file-size').text(self.options.str_uploading_no_limit);
      }

      /* Initiate the Blueimp File Upload plugin*/
      upload_box.fileupload({
        url: self.options.ajax_url,
        type: method_val,
        maxChunkSize: maxchunksize_val,
        singleFileUploads: singlefileuploads_val,
        multipart: multipart_val,
        dataType: 'json',
        autoUpload: autoUpload,
        maxFileSize: (support_xhr && self.element.find('input[name="maxfilesize"]').attr('data-limit') === '0') ? 'undefined' : self.options.post_max_size,
        acceptFileTypes: new RegExp(self.element.find('input[name="acceptfiletypes"]').val(), "i"),
        dropZone: self.element,
        messages: {
          maxNumberOfFiles: self.options.maxNumberOfFiles,
          acceptFileTypes: self.options.acceptFileTypes,
          maxFileSize: self.options.maxFileSize,
          minFileSize: self.options.minFileSize
        },
        limitConcurrentUploads: 3,
        disableImageLoad: true,
        disableImageResize: true,
        disableImagePreview: true,
        disableAudioPreview: true,
        disableVideoPreview: true,
        uploadTemplateId: null,
        downloadTemplateId: null,
        add: function (e, data) {

          $.each(data.files, function (index, file) {
            self.number_of_uploaded_files.Counter++;
            file.hash = file.name.hashCode() + '_' + Math.floor(Math.random() * 1000000);
            file.listtoken = self.options.listtoken;
            file = self._uploadValidateFile(file);
            var row = self._uploadRenderRow(file);

            if (file.error !== false) {
              self.number_of_uploaded_files.Counter--;
              upload_box.trigger('useyourdrive-removeupload', [data.files[index]]);
              data.files.splice(index, 1);
            }

            row.find('.cancel-upload').on('click', function (e) {
              e.preventDefault();
              e.stopPropagation();

              data.files.splice(index, 1);
              self.number_of_uploaded_files.Counter--;
              self._uploadDeleteRow(file, 0);

              upload_box.trigger('useyourdrive-removeupload', [data.files[index]]);

            });
          });

          upload_box.trigger('useyourdrive-addupload', [data]);

          if (data.autoUpload || (data.autoUpload !== false &&
                  $(this).fileupload('option', 'autoUpload'))) {
            if (data.files.length > 0) {
              data.process().done(function () {
                self._uploadDoRequest(data);
              });
            }
          } else {
            $(this).on('useyourdrive-startupload', function () {
              if (data.files.length > 0) {
                data.process().done(function () {
                  self._uploadDoRequest(data);
                });
              }
            });
          }
        },
        submit: function (e, data) {

          /*  Enable navigation prompt */
          window.onbeforeunload = function () {
            return true;
          };

          var filehash;
          var file;

          $.each(data.files, function (index, entry) {
            file = entry;
            self._uploadRenderRowOnStart(file);
            filehash = file.hash;
          });

          /* Do Direct Upload */
          if (support_xhr) {
            var $this = $(this);
            $.ajax({type: "POST",
              url: UseyourDrive_vars.ajax_url,
              data: {
                action: 'useyourdrive-upload-file',
                type: 'get-direct-url',
                filename: file.name,
                file_size: file.size,
                mimetype: file.type,
                orgin: (!window.location.origin) ? window.location.protocol + "//"
                        + window.location.hostname
                        + (window.location.port ? ':' + window.location.port : '') : window.location.origin,
                lastFolder: self.element.attr('data-id'),
                listtoken: self.options.listtoken,
                _ajax_nonce: self.options.upload_nonce,
              },
              error: function () {
                file.error = self.options.str_error;
                self._uploadRenderRowOnFinish(file);
              },
              success: function (response) {
                if (typeof response.result === 'undefined' || typeof response.url === 'undefined') {
                  file.error = self.options.str_error;
                  self._uploadRenderRowOnFinish(file);
                } else {
                  data.url = response.url;
                  file.convert = response.convert;
                  data.jqXHR = $this.fileupload('send', data);
                }
              },
              dataType: 'json'
            });
            return false;

            /* Do Upload via Server */
          } else {
            data.formData = {
              action: 'useyourdrive-upload-file',
              type: 'do-upload',
              hash: file.hash,
              lastFolder: self.element.attr('data-id'),
              listtoken: self.options.listtoken,
              _ajax_nonce: self.options.upload_nonce
            };
          }

        }
      }).on('fileuploadsubmit', function (e, data) {

      }).on('fileuploadprogress', function (e, data) {

        var file = data.files[0];
        if (support_xhr) {
          /* Upload Progress for direct upload */
          var progress = parseInt(data.loaded / data.total * 100, 10);
          self._uploadRenderRowOnProgress(file, {percentage: progress, progress: 'uploading_to_cloud'});
        } else {
          /* Upload Progress for upload via server*/
          var progress = parseInt(data.loaded / data.total * 100, 10) / 2;

          self._uploadRenderRowOnProgress(file, {percentage: progress, progress: 'uploading_to_server'});

          if (progress >= 50) {
            self._uploadRenderRowOnProgress(file, {percentage: 50, progress: 'uploading_to_cloud'});

            setTimeout(function () {
              self._uploadGetProgress(file);
            }, 2000);
          }
        }

      }).on('fileuploadstopped', function () {
        $('.gform_button:submit').prop("disabled", false).fadeTo(400, 1);
        $('.wpcf7-submit').prop("disabled", false).fadeTo(400, 1);
      }).on('fileuploaddone', function (e, data) {
        sendDriveGooglePageView('Upload file');
      }).on('fileuploadalways', function (e, data) {

        var file = data.files[0];
        if (data.result === null) {
          file.error = self.options.str_error;
          self._uploadRenderRowOnFinish(file);
        }

        if (support_xhr) {
          /* Final Event after upload for Direct upload */
          file.fileid = data.result.id;
          file.completepath = '';
          file.filesize = self._helperFormatBytes(data.result.size, 1);
          file.link = data.result.webViewLink;

          if (file.convert) {
            self._uploadDoConvert(file);
          } else {
            self._uploadRenderRowOnFinish(file);
          }
        } else {
          /* Final Event after upload for upload via Server*/
          if (typeof data.result !== 'undefined') {
            if (typeof data.result.status !== 'undefined') {
              if (data.result.status.progress === 'finished' || data.result.status.progress === 'failed') {
                self._uploadRenderRowOnFinish(data.result.file);
              }
            } else {
              data.result.file.error = self.options.str_error;
              self._uploadRenderRowOnFinish(data.result.file);
            }
          } else {
            file.error = self.options.str_error;
            self._uploadRenderRowOnFinish(file);
          }
        }

      }).on('fileuploaddrop', function (e, data) {
        var uploadcontainer = $(this);
        $('html, body').animate({
          scrollTop: uploadcontainer.offset().top
        }, 1500);
      }).on('useyourdrive-upload-finished', function (e) {
        upload_form.find('input[type="submit"].firing, input[type="button"].gform_next_button.firing, button[id^="gform_submit_button"]').prop("disabled", false).trigger('click');
        $('input[type="submit"]').prop('disabled', false).fadeTo(400, 1);
      });
    },

    _getFileList: function (data) {
      var request = this._buildFileListRequest();

      this.element.find('.no_results').remove();
      this.options.loadingContainer.removeClass('initialize upload error').fadeIn(400);

      this.element.find('.nav-refresh i').addClass('fa-spin');
      request(data, this.renderBrowserContent, this);
    },

    _buildFileListRequest: function (data) {

      var self = this;

      return  this._pipeline({
        url: self.options.ajax_url,
        type: "POST",
        dataType: "json",
        data: function (d) {

          d.listtoken = self.options.listtoken;
          d.lastFolder = self.element.attr('data-id');
          d.folderPath = self.element.attr('data-path');
          d.sort = self.element.attr('data-sort');
          d.deeplink = self.element.attr('data-deeplink');
          d.filelayout = self.element.attr('data-layout');
          d.action = 'useyourdrive-get-filelist';
          d._ajax_nonce = self.options.refresh_nonce;
          d.mobile = self.options.mobile;

          if (self.element.attr('data-list') === 'gallery') {
            d.action = 'useyourdrive-get-gallery';
            d._ajax_nonce = self.options.gallery_nonce;
          }

          d.query = self.searchQuery;

          return d;
        }
      });
    },

    /**
     * Initiate the Search Box functionality
     */
    _initSearchBox: function () {
      var self = this;
      var $nav_search_box = this.element.find('.nav-search');

      /* Search qtip popup */
      $nav_search_box.qtip({
        prerender: false,
        id: 'search-' + self.options.listtoken,
        content: {
          text: $nav_search_box.next('.search-div'),
          button: $nav_search_box.next('.search-div').find('.search-remove')
        },
        position: {
          my: 'top right',
          at: 'bottom center',
          target: $nav_search_box,
          viewport: $(window),
          adjust: {
            scroll: false
          }
        },
        style: {
          classes: 'UseyourDrive search ' + self.options.content_skin
        },
        show: {
          effect: function () {
            $(this).fadeTo(90, 1, function () {
              $('input', this).focus();
            });
          }
        },
        hide: {
          fixed: true,
          delay: 1500
        }
      });

      /* Search Key Up event */
      self.element.find('.search-input').on("keyup", function (event) {

        self.searchQuery = $(this).val();

        if ($(this).val().length > 0) {
          self.options.loadingContainer.addClass('search');
          self.element.find('.nav-search').addClass('inuse');

          clearTimeout(self.updateTimer);
          self.updateTimer = setTimeout(function () {
            self.element.find('.loading, .ajax-filelist').show();
            self._getFileList({});
          }, 1000);

        } else {
          self.element.find('.nav-search').removeClass('inuse');
          if (self.element.hasClass('searchlist')) {
            self.element.find('.loading, .ajax-filelist').hide();
            self.element.find('.ajax-filelist').html('');
          }
        }
      });

      /* Search submit button event [Search Mode] */
      self.element.find('.submit-search').click(function () {

        self.searchQuery = $(this).val();

        if ($(this).val().length > 0) {

          clearTimeout(self.updateTimer);
          self.updateTimer = setTimeout(function () {
            self.element.find('.loading, .ajax-filelist').show();
            self._getFileList({});
          }, 1000);

        } else {
          self.element.find('.loading, .ajax-filelist').hide();
          self.element.find('.ajax-filelist').html('');
        }
      });

      self.element.find('.search-remove').click(function () {
        if ($(this).parent().find('.search-input').val() !== '') {
          self.clearSearchBox();
        }
      });

    },

    clearSearchBox: function () {
      $('[data-qtip-id="search-' + this.options.listtoken + '"] .search-input').val('').trigger('keyup');
    },

    /* Initiate the Settings menu functionality */
    _initGearMenu: function () {
      var self = this;
      var $gearmenu = this.element.find('.nav-gear');

      $gearmenu.qtip({
        prerender: false,
        id: 'nav-' + self.options.listtoken,
        content: {
          text: $gearmenu.next('.gear-menu')
        },
        position: {
          my: 'top right',
          at: 'bottom center',
          target: $gearmenu,
          viewport: $(window),
          adjust: {
            scroll: false
          }
        },
        style: {
          classes: 'UseyourDrive ' + self.options.content_skin
        },
        show: {
          event: 'click, mouseenter',
          solo: true
        },
        hide: {
          event: 'mouseleave unfocus',
          fixed: true,
          delay: 200
        },
        events: {
          show: function (event, api) {
            var selectedboxes = self._helperReadArrCheckBoxes("[data-token='" + self.options.listtoken + "'] input[name='selected-files[]']");

            if (selectedboxes.length === 0) {
              api.elements.content.find(".selected-files-to-zip").parent().hide();
              api.elements.content.find(".all-files-to-zip").parent().show();
              api.elements.content.find(".selected-files-delete").parent().hide();
            } else {
              api.elements.content.find(".selected-files-to-zip").parent().show();
              api.elements.content.find(".all-files-to-zip").parent().hide();
              api.elements.content.find(".selected-files-delete").parent().show();
            }

            var visibleelements = api.elements.content.find('ul > li').not('.gear-menu-no-options').filter(function () {
              return $(this).css('display') !== 'none';
            });

            if (visibleelements.length > 0) {
              api.elements.content.find('.gear-menu-no-options').hide();
            } else {
              api.elements.content.find('.gear-menu-no-options').show();
            }

          }
        }
      });


      /* Layout button is the Switch between table and grid mode */
      this.element.find('.nav-layout').unbind('click').click(function () {

        if (self.element.attr('data-layout') === 'list') {
          self.element.attr('data-layout', 'grid');
        } else {
          self.element.attr('data-layout', 'list');
        }

        self._getFileList({});
      });

      /* Zip button*/
      this.element.find('.select-all-files').unbind('click').click(function () {
        self.element.find(".selected-files:checkbox").prop("checked", $(this).prop("checked"));
        if ($(this).prop("checked") === true) {
          self.element.find('.entry:not(".newfolder")').addClass('isselected');
        } else {
          self.element.find('.entry:not(".newfolder")').removeClass('isselected');
        }
      });

      this.element.find('.all-files-to-zip, .selected-files-to-zip').unbind('click').click(function (event) {

        var entries = [];

        if ($(event.target).hasClass('all-files-to-zip')) {
          self.element.find('.select-all-files').trigger('click');
          entries = self._helperReadArrCheckBoxes("[data-token='" + self.options.listtoken + "'] input[name='selected-files[]']");
        }

        if ($(event.target).hasClass('selected-files-to-zip')) {
          entries = self._helperReadArrCheckBoxes("[data-token='" + self.options.listtoken + "'] input[name='selected-files[]']");
        }

        var data = {
          action: 'useyourdrive-create-zip',
          listtoken: self.options.listtoken,
          lastFolder: self.element.attr('data-id'),
          _ajax_nonce: self.options.createzip_nonce,
          files: entries
        };


        var $processor_icon = $('<div class="processor_icon"><i class="fas fa-cog fa-spin fa-1x fa-fw"></i></div>').css({'margin-right': '5px', 'display': 'inline-grid'});
        self.element.find(".layout-grid input:checked[name='selected-files[]']").closest('.entry').find(".entry-name-view").prepend($processor_icon);
        self.element.find(".layout-list input:checked[name='selected-files[]']").closest('.entry').find(".entry_name").prepend($processor_icon);

        self.element.find('.processor_icon').delay(5000).fadeOut('slow', function () {
          $(this).remove();
        });

        if ($(event.target).hasClass('all-files-to-zip')) {
          self.element.find('.select-all-files').trigger('click');
        }

        $('.qtip').qtip('hide');
        $(this).attr('href', self.options.ajax_url + "?" + $.param(data));

        return;
      });

      /* Add scroll event to nav-upload */
      self.element.find('.nav-upload').click(function () {
        $('.qtip').qtip('hide');

        var uploadcontainer = self.element.find('.fileupload-container');

        $('html, body').animate({
          scrollTop: uploadcontainer.offset().top
        }, 1500);
        for (var i = 0; i < 3; i++) {
          uploadcontainer.find('.fileupload-buttonbar').fadeTo('slow', 0.5).fadeTo('slow', 1.0);
        }
      });

      /* Delete multiple files at once */
      self.element.find('.selected-files-delete').click(function () {
        $('.qtip').qtip('hide');

        var entries = self.element.find("input[name='selected-files[]']:checked");

        if (entries.length === 0) {
          return false;
        }

        self._actionDeleteEntries(entries);
      });

      /* Social Share Folder */
      self.element.find('.entry_action_shortlink_folder').unbind('click').click(function (e) {
        self._actionShareEntry($(this));
      });
    },

    /**
     * Render the Content after receving the File List
     */
    renderBrowserContent: function (self, json) {
      if (json === false) {
        self.element.find('.nav-title').html(self.options.str_no_filelist);
        self.options.loadingContainer.addClass('error');
      } else {
        self.options.loadingContainer.fadeIn(200);


        self.element.find('.ajax-filelist').html(json.html);
        self.element.find('.image-collage').hide();
        self.element.find('.nav-title').html(json.breadcrumb);
        self.element.find('.current-folder-raw').text(json.rawpath);

        if (json.lastFolder !== null) {
          self.element.attr('data-id', json.lastFolder);
        }

        if (json.folderPath !== null) {
          self.element.attr('data-path', json.folderPath);
        }

      }

      self.element.find('.nav-refresh i').removeClass('fa-spin');

      self.unveilImages();

      if (self.element.hasClass('gridgallery')) {
        self.renderContentForGallery();
        self._initLazyLoading();
      } else {
        self.renderContentForBrowser();
      }

      self.unveilImages();

      /* Hover Events */
      self.element.find('.entry').unbind('hover').hover(
              function () {
                $(this).addClass('hasfocus');
              },
              function () {
                $(this).removeClass('hasfocus');
              }
      ).on('mouseover', function () {
        $(this).addClass('hasfocus');
      }).unbind('click').click(function () {
        /* CheckBox Event */
        //$(this).find('.entry_checkbox input[type="checkbox"]').trigger('click');
      }).on("contextmenu", function (e) {
        /* Disable right clicks */
        return false;
      });

      /* Folder Click events */
      self.element.find('.folder, .image-folder').unbind('click').click(function (e) {

        if ($(this).hasClass('isdragged') || $(this).hasClass('newfolder')) {
          return false;
        }

        e.stopPropagation();
        self.clearSearchBox();

        var data = {
          id: $(this).closest('.folder, .image-folder').attr('data-id'),
        };

        self._getFileList(data);

        $('html, body').stop().animate({
          scrollTop: self.element.offset().top - 150
        }, 1000);

      });

      /* Create New Folder Event */
      self.element.find('.newfolder').unbind('click').click(function (e) {
        self._actionCreateEntry($(this));
      });

      /* CheckBox Events */
      self.element.find('.entry_checkbox').unbind('click').click(function (e) {
        e.stopPropagation();
        return true;
      });

      self.element.find('.entry_checkbox :checkbox').click(function (e) {
        if ($(this).prop('checked')) {
          $(this).closest('.entry').addClass('isselected');
        } else {
          $(this).closest('.entry').removeClass('isselected');
        }
      });


      self._initEditMenu();
      self._initDescriptionBox();
      self._initLightbox();
      self.renderThumbnailsPopup();
      self._initMove();
      self._initLinkEvent();
      self._initScrollToTop();

      self.options.loadingContainer.fadeOut(300);
    },

    renderContentForBrowser: function () {
      var self = this;

      switch (this.element.attr('data-layout')) {
        case 'list':
          self.element.removeClass('uyd-grid').addClass('uyd-list');
          $(".qtip[data-qtip-id='nav-" + self.options.listtoken + "']").find('.fa-th-large').closest('li').show();
          self.element.find('.fa-th-large').closest('li').show();
          $(".qtip[data-qtip-id='nav-" + self.options.listtoken + "']").find('.fa-th-list').closest('li').hide();
          self.element.find('.fa-th-list').closest('li').hide();
          break;

        case 'grid':
          self.element.removeClass('uyd-list').addClass('uyd-grid');
          $(".qtip[data-qtip-id='nav-" + self.options.listtoken + "']").find('.fa-th-large').closest('li').hide();
          self.element.find('.fa-th-large').closest('li').hide();
          $(".qtip[data-qtip-id='nav-" + self.options.listtoken + "']").find('.fa-th-list').closest('li').show();
          self.element.find('.fa-th-list').closest('li').show();

          /* Update items to fit in container */
          var $grid_container = self.element.find('.files.layout-grid');
          var targetwidth = 175;
          var filelistwidth = $grid_container.innerWidth() - 1;
          var itemsonrow = Math.ceil(filelistwidth / targetwidth);
          var calculatedwidth = Math.floor(filelistwidth / itemsonrow);

          $grid_container.removeWhitespace();

          self.element.find('.entry_block').each(function () {
            var padding = parseInt($(this).css('padding-left')) + parseInt($(this).css('padding-right'));
            $(this).parent().outerWidth(calculatedwidth - padding, true);
          });

          $grid_container.fadeTo(0, 0).delay(100).fadeTo(200, 1);

          break;
      }
    },

    renderContentForGallery: function () {
      var self = this;

      var image_container = self.element.find('.image-container');
      var image_collage = self.element.find(".image-collage");

      image_container.hover(
              function () {
                $(this).find('.image-rollover').stop().animate({opacity: 1}, 400);
              },
              function () {
                $(this).find('.image-rollover').stop().animate({opacity: 0}, 400);
              }).find('.image-rollover').css("opacity", "0");

      image_collage.outerWidth(self.element.find('.ajax-filelist').width() - 1, true);

      var targetheight = self.element.attr('data-targetheight');
      image_collage.removeWhitespace().collagePlus({
        'targetHeight': targetheight,
        'fadeSpeed': "slow",
        'allowPartialLastRow': true
      });

      self.element.find(".image-container.hidden").fadeOut(0);
      image_collage.fadeTo(200, 1);

      image_container.each(function () {
        var folder_thumb = $(this).find(".folder-thumb");

        $(this).find(".image-folder-img").width($(this).width()).height($(this).height());

        if (folder_thumb.length > 0) {
          folder_thumb.width($(this).width()).height($(this).height());
          $(this).find(".image-folder-img").hide();
        }
      });

      if (self.element.find('.loadthumbs').length > 0) {
        self.loadImageFoldersThumbs();
      }

      self.renderImageFolders();
    },

    loadImageFoldersThumbs: function () {
      var self = this;

      var folders = self.element.find('.loadthumbs').removeClass('loadthumbs').map(function () {
        return $(this).data('id');
      }).get();


      $.ajax({type: "POST",
        url: self.options.ajax_url,
        data: {
          action: 'useyourdrive-thumbnail',
          type: 'folder-thumbnails',
          listtoken: self.options.listtoken,
          lastFolder: self.element.attr('data-id'),
          folderPath: self.element.attr('data-path'),
          folders: folders
        },
        success: function (response) {

          $.each(response, function (i, html) {
            if (html === '') {
              return true;
            }

            var folder_element = self.element.find('[data-id="' + i + '"]');
            folder_element.find('.folder-thumb').remove();
            folder_element.find('.image-folder-img').hide().after(html);
            folder_element.find('.folder-thumb').width($(folder_element).width()).height($(folder_element).height()).first().fadeIn(1500);
          });

        },
        dataType: 'json'
      });

    },

    renderImageFolders: function () {
      var self = this;

      self.element.find('.image-folder').unbind('mousemove').mousemove(function (e) {

        var thumbnails = $(this).find('.folder-thumb');
        var relX = e.offsetX / e.currentTarget.offsetWidth;
        var show_n = Math.ceil(relX * thumbnails.length) - 1;

        thumbnails.filter(':gt(0)').stop(true).fadeOut().filter(':eq(' + show_n + ')').stop(true).fadeIn();
      });

      self.element.find('.image-folder').unbind('mouseleave').mouseleave(function () {
        $(this).find('.folder-thumb:gt(0)').stop(true).fadeOut();
      });


    },

    /* Load more images */
    _initLazyLoading: function () {
      var self = this;
      var last_visible_image = self.element.find(".image-container.entry:not(.hidden):last()");
      var load_per_time = self.element.attr('data-loadimages');

      last_visible_image.one('inview', function (event, isInView) {
        var images = self.element.find(".image-container:hidden:lt(" + load_per_time + ")");

        if (images.length === 0) {
          return;
        }

        images.fadeIn(500).removeClass('hidden').find('img').removeClass('hidden');
        self.unveilImages();

        self._initLazyLoading();
      });
    },

    _initScrollToTop: function () {
      var self = this;

      if (self.element.find('.ajax-filelist').length === 0) {
        return;
      }

      self.element.find('.scroll-to-top').unbind('click').click(function () {
        $('html, body').animate({
          scrollTop: self.element.offset().top
        }, 1500);
      });

      $(window).off('scroll', null, self._positionScrollToTop).on('scroll', null, {}, self._positionScrollToTop);
    },

    _positionScrollToTop: function (event) {
      clearTimeout(window.scrollTimer);

      window.scrollTimer = setTimeout(function () {

        $('.ajax-filelist').each(function () {
          var $container = $(this);
          var $scroll_to_top_container = $container.next('.scroll-to-top');

          var heightContainer = $container.height();
          var positionContainer = $container.offset();
          var bottomContainer = positionContainer.top + heightContainer;
          var topWindow = $(window).scrollTop();
          var bottomWindow = topWindow + $(window).height();

          if (topWindow > positionContainer.top && heightContainer > $(window).height()) {
            $scroll_to_top_container.show().fadeIn(40);

            var positionbutton = heightContainer;
            if (bottomContainer > bottomWindow) {
              positionbutton = bottomWindow - positionContainer.top - 30;
            }
            $scroll_to_top_container.stop().animate({top: Math.round(positionbutton - 50)});
          } else {
            $scroll_to_top_container.fadeOut(400);
          }
        });
      }, 50);
    },

    /**
     * Initiate the UI Moveable / Draggable function
     * to allow the user to move files and folders
     * @returns {Boolean}
     */
    _initMove: function () {
      var self = this;
      if (this.element.find('.moveable').length === 0) {
        return false;
      }

      this.element.find('.moveable').draggable({
        stack: ".moveable",
        cursor: 'move',
        cursorAt: {top: 10, left: 10},
        containment: 'parent',
        helper: "clone",
        distance: 10,
        delay: 50,
        start: function (event, ui) {
          $(this).addClass('isdragged');
        },
        stop: function (event, ui) {
          setTimeout(function () {
            $(this).removeClass('isdragged');
          }, 300);
        }
      });
      this.element.find('.folder').droppable({
        accept: self.element.find('.moveable'),
        activeClass: "ui-state-hover",
        hoverClass: "ui-state-active",
        tolerance: "pointer",
        drop: function (event, ui) {
          //ui.draggable.fadeOut();
          self._actionMoveEntry(ui.draggable, $(this));
        }
      });
    },

    /* Button Events for linking folders */
    _initLinkEvent: function () {
      var self = this;

      self.element.find('.entry_linkto').unbind('click').click(function (e) {

        var folder_text = $(this).parent().attr('data-name');
        var folder_id = $(this).parent().attr('data-id');
        var user_id = $('.useyourdrive .thickbox_opener').find('[data-user-id]').attr('data-user-id');
        var $thickbox_opener = $('.thickbox_opener');

        if ($thickbox_opener.hasClass('private-folders-auto')) {
          $thickbox_opener.find('.private-folders-auto-current').val(folder_text);
          $thickbox_opener.find('.private-folders-auto-input-id').val(folder_id);
          $thickbox_opener.find('.private-folders-auto-input-name').val(folder_text);
          $thickbox_opener.find('.private-folders-auto-button').removeClass('disabled').find('.uyd-spinner').fadeOut()
          tb_remove();
          e.stopPropagation();
          return true;
        }

        if ($thickbox_opener.hasClass('woocommerce_upload_location')) {
          $('#woocommerce_useyourdrive-woocommerce_upload_location_id').val(folder_id);
          $('#woocommerce_useyourdrive-woocommerce_upload_location').val(folder_text);
          tb_remove();
          e.stopPropagation();
          return true;
        }

        $.ajax({type: "POST",
          url: self.options.ajax_url,
          data: {
            action: 'useyourdrive-linkusertofolder',
            id: folder_id,
            text: folder_text,
            userid: user_id,
            _ajax_nonce: self.options.createlink_nonce
          },
          beforeSend: function () {
            tb_remove();
            $('.useyourdrive .thickbox_opener').find('.uyd-spinner').show();
          },
          complete: function () {
            $('.uyd-spinner').hide();
          },
          success: function (response) {
            if (response === '1') {
              $('.useyourdrive .thickbox_opener').parent().find('.column-private_folder').text(folder_text);
              $('.useyourdrive .thickbox_opener .unlinkbutton').removeClass('hidden');
              $('.useyourdrive .thickbox_opener .linkbutton').addClass('hidden');
              $('.useyourdrive .thickbox_opener').removeClass("thickbox_opener");
            } else {
              location.reload(true);
            }
          },
          dataType: 'text'
        });

        e.stopPropagation();
        return true;
      });

      self.element.find('.entry_woocommerce_link').unbind('click').click(function (e) {

        var file_id = $(this).closest('.entry').attr('data-id');
        var file_name = $(this).closest('.entry').attr('data-name');

        tb_remove();
        window.wc_useyourdrive.afterFileSelected(file_id, file_name);
        e.stopPropagation();
        return true;
      });
    },

    /* Bind event which shows the edit menu */
    _initEditMenu: function () {
      var self = this;
      self.element.find(' .entry .entry_edit_menu').each(function () {

        $(this).click(function (e) {
          e.stopPropagation();
        });

        $(this).qtip({
          content: {
            text: $(this).next('.uyd-dropdown-menu')
          },
          position: {
            my: 'top center',
            at: 'bottom center',
            target: $(this),
            scroll: false,
            viewport: self.element
          },
          show: {
            event: 'click',
            solo: true
          },
          hide: {
            event: 'mouseleave unfocus',
            delay: 200,
            fixed: true
          },
          events: {
            show: function (event, api) {
              api.elements.target.closest('.entry').addClass('hasfocus').addClass('popupopen');
            },
            hide: function (event, api) {
              api.elements.target.closest('.entry').removeClass('hasfocus').removeClass('popupopen');
            }
          },
          style: {
            classes: 'UseyourDrive ' + self.options.content_skin
          }
        });
      });

      /* Preview Event */
      self.element.find('.entry_action_view').unbind('click').click(function () {
        self._actionPreviewEntry($(this));
      });

      /* Download Event */
      self.element.find('.entry_action_download').unbind('click').click(function (e) {
        self._actionDownloadEntry($(this));
      });

      /* Save As Event */
      self.element.find('.entry_action_export').unbind('click').click(function (e) {
        self._actionExportEntry(e, $(this));
      });

      /* Social Share Event */
      self.element.find('.entry_action_shortlink').unbind('click').click(function (e) {
        self._actionShareEntry($(this));
      });

      /* Delete Event*/
      self.element.find('.entry_action_delete').unbind('click').click(function (e) {
        var dataid = $(this).closest("ul").attr('data-id');
        var $checkbox = self.element.find(".entry[data-id='" + dataid + "'] .selected-files:checkbox");
        self._actionDeleteEntries([$checkbox]);
      });

      /* Rename Event */
      self.element.find('.entry_action_rename').unbind('click').click(function (e) {
        self._actionRenameEntry($(this));
      });

      /* Description Box Event */
      self.element.find('.entry_action_description').unbind('click').click(function (e) {
        self._actionEditDescriptionEntry(e, $(this));
      });

    },

    _initDescriptionBox: function () {
      var self = this;

      self.element.find('.entry .entry_description').each(function () {
        $(this).click(function (e) {
          e.stopPropagation();
        });

        $(this).qtip({
          content: {
            text: $(this).next('.description_textbox')
          },
          position: {
            my: 'top center',
            at: 'bottom center',
            target: $(this),
            scroll: false,
            viewport: self.element
          },
          show: {
            delay: 200,
            solo: true
          },
          hide: {
            event: 'mouseleave unfocus',
            delay: 200,
            fixed: true
          },
          events: {
            show: function (event, api) {
              api.elements.target.closest('.entry').addClass('hasfocus').addClass('popupopen');
            },
            hide: function (event, api) {

              if (api.elements.content.find('.description_textarea').length > 0) {
                var html = api.elements.content.find('.description_textarea').val().replace(/\r\n|\r|\n/g, "<br />");
                var viewableText = $("<div>").addClass('description_text');
                viewableText.html(html);
                api.elements.content.find('.description_textarea').replaceWith(viewableText);
                api.elements.content.find('input[type=button]').remove();
                api.elements.content.find('.ajaxprocess').remove();
                api.elements.content.find('.entry_action_description').show();
              }
              api.elements.target.closest('.entry').removeClass('hasfocus').removeClass('popupopen');
            }
          },
          style: {
            classes: 'UseyourDrive description ' + self.options.content_skin
          }
        });
      });
    },

    /* Bind event which shows popup with thumbnail on hover in file list */
    renderThumbnailsPopup: function () {
      var self = this;
      self.element.find('.entry[data-tooltip] .entry_name, .entry[data-tooltip] .entry_lastedit').each(function () {
        $(this).qtip({
          suppress: true,
          content: {
            text: function (event, api) {
              var descriptionbox = $(this).parent().find('.description_textbox').clone();
              descriptionbox.find("img.preloading").removeClass('hidden').unveil(200, null, function () {
                $(this).load(function () {
                  $(this).removeClass('preloading').removeAttr('data-src');
                  $(this).prev('.preloading').remove();
                });
              });

              return descriptionbox;
            }
          },
          position: {
            target: 'mouse',
            adjust: {x: 5, y: 5, scroll: false},
            viewport: self.element
          },
          show: {
            delay: 500,
            solo: true
          },
          hide: {
            event: 'click mouseleave unfocus'
          },
          style: {
            classes: 'UseyourDrive description ' + self.options.content_skin
          }
        });
      });
    },

    /* Unveil Images */
    unveilImages: function () {
      var self = this;

      self.element.find('img.preloading').one('error', function () {
        this.src = $(this).attr('data-src-backup');
        $(this).removeAttr('data-src');
        $(this).prev('.preloading').remove();
      });

      self.element.find('img.preloading').not('.hidden').unveil(200, null, function () {
        $(this).load(function () {
          $(this).removeClass('preloading').removeAttr('data-src');
          $(this).prev('.preloading').remove();
        });
      });

      setTimeout(function () {
        //self.renderContentForGallery()
      }, 200);

    },

    /**
     * Renders the General Menu in the Navigation Bar
     */
    renderNavMenu: function () {
      var self = this;

      /* Fire up the search functionality*/
      this._initSearchBox();
      this._initGearMenu();

      /* Refresh button does a hard refresh for the current folder*/
      this.element.find('.nav-refresh').unbind('click');
      this.element.find('.nav-refresh').click(function () {
        self.clearSearchBox();
        self.options.forceRefresh = true;
        self._getFileList({});
      });

      /* Event for nav-home button */
      this.element.find('.nav-home').unbind('click');
      this.element.find('.nav-home').click(function () {
        self.clearSearchBox();
        self.element.attr('data-id', self.element.attr('data-org-id')).attr('data-path', '');
        self._getFileList({'lastFolder': self.element.attr('data-org-id')});
      });

      /* Sortable column Names */
      self.element.find('.sortable').click(function () {

        var newclass = 'asc';
        if ($(this).hasClass('asc')) {
          newclass = 'desc';
        }

        self.element.find('.sortable').removeClass('asc').removeClass('desc');
        $(this).addClass(newclass);
        var sortstr = $(this).attr('data-sortname') + ':' + newclass;
        self.element.attr('data-sort', sortstr);

        self._getFileList({});
      });
    },

    /**
     * Open the Lightbox to preview an entry
     * @param {Object} entry_for_focus
     * @param {Array} rows // array of objects for gallery mode
     * @returns {undefined}
     */
    _initLightbox: function () {
      var self = this;

      if (!$.isEmptyObject(this.lightBox)) {
        this.lightBox.destroy();
      }

      this.lightBox = self.element.find('.ilightbox-group').iLightBox({

        skin: this.options.lightbox_skin,
        path: this.options.lightbox_path,
        maxScale: 1,
        slideshow: {
          pauseOnHover: true,
          pauseTime: self.element.attr('data-pausetime'),
          startPaused: ((self.element.attr('data-list') === 'gallery') && (self.element.attr('data-slideshow') === '1')) ? false : true
        },
        controls: {
          slideshow: (self.element.attr('data-list') === 'gallery') ? true : false,
          arrows: true,
          thumbnail: (self.options.mobile ? false : true)
        },
        caption: {
          start: (self.options.lightbox_showcaption === 'mouseenter') ? true : false,
          show: self.options.lightbox_showcaption,
          hide: (self.options.lightbox_showcaption === 'mouseenter') ? 'mouseleave' : self.options.lightbox_showcaption,
        },
        keepAspectRatio: true,
        callback: {
          onBeforeLoad: function (api, position) {
            $('.ilightbox-holder').addClass('UseyourDrive');
            $('.ilightbox-holder .uyd-hidepopout').remove();

            var element = $('.ilightbox-holder').find('iframe').addClass('uyd-embedded');

            if (self.element.attr('data-popout') === '0') {
              $('.ilightbox-holder').find('.uyd-embedded').after('<div class="uyd-hidepopout">&nbsp;</div>');
            }

            self._helperIframeFix(element);
          },
          onBeforeChange: function () {
            /* Stop all HTML 5 players */
            var players = $('.ilightbox-holder video, .ilightbox-holder audio');
            $.each(players, function (i, element) {
              if (element.paused === false) {
                element.pause();
              }
            });
          },
          onAfterChange: function (api) {
            /* Auto Play new players*/
            var players = api.currentElement.find('video, audio');
            $.each(players, function (i, element) {
              if (element.paused) {
                element.play();
              }
            });
          },
          onRender: function (api, position) {
            /* Auto-size HTML 5 player */
            var $video_html5_players = $('.ilightbox-holder').find('video, audio');
            $.each($video_html5_players, function (i, video_html5_player) {

              var $video_html5_player = $(this);

              video_html5_player.addEventListener('playing', function () {
                var container_width = api.currentElement.find('.ilightbox-container').width() - 1;
                var container_height = api.currentElement.find('.ilightbox-container').height() - 1;

                $video_html5_player.width(container_width);

                $video_html5_player.parent().width(container_width)

                if ($video_html5_player.height() > api.currentElement.find('.ilightbox-container').height() - 2) {
                  $video_html5_player.height(container_height);
                }
              }, false);
              $video_html5_player.find('source').attr('src', $video_html5_player.find('source').attr('data-src'));
            });

          },
          onShow: function (api) {
            if (api.currentElement.find('.empty_iframe').length === 0) {
              api.currentElement.find('.uyd-embedded').after(self.options.str_iframe_loggedin);
            }

            /* Bugfix for PDF files that open very narrow */
            if (api.currentElement.find('iframe').length > 0) {
              setTimeout(function () {
                api.currentElement.find('.uyd-embedded').width(api.currentElement.find('.ilightbox-container').width() - 1);
              }, 500);
              api.currentElement.find('iframe').on('load', function () {
                api.currentElement.find('.empty_iframe').remove();
              });
            }

            api.currentElement.find('.empty_iframe').hide();
            if (api.currentElement.find('img').length === 0) {
              setTimeout(function () {
                api.currentElement.find('.empty_iframe').fadeIn();
                api.currentElement.find('.empty_iframe_link').attr('href', api.currentElement.find('iframe').attr('src'))
              }, 5000);
            }

            /* Auto Play new players*/
            var players = api.currentElement.find('video, audio');
            $.each(players, function (i, element) {
              if (element.paused) {
                element.play();
              }
            });

            /* Lazy Load thumbnails */
            var iL = this;

            $(".ilightbox-thumbnail img.preloading").unveil(null, null, function () {
              $(this).load(function () {
                $(this).removeClass('preloading').removeAttr('data-src');
                $(this).prev('.preloading').remove();
                $(this).parent().data({
                  naturalWidth: this.width,
                  naturalHeight: this.height
                });

                iL.positionThumbnails(null, null, null);
              });
            });

            $('.ilightbox-container .uyd-hidepopout').on("contextmenu", function (e) {
              return false;
            });

            $('.ilightbox-container img').on("contextmenu", function (e) {
              return (self.options.lightbox_rightclick === 'Yes');
              ;
            });

            if (self.options.mobile) {
              $('.ilightbox-container img').panzoom({disablePan: true, minScale: 1, contain: 'invert'});
              $('.ilightbox-container img').on('panzoomzoom', function (e, panzoom, scale) {
                if (scale == 1) {
                  panzoom.options.disablePan = true;
                } else {
                  panzoom.options.disablePan = false;
                }
              });
            }

            /* Log preview event if needed */
            var $img = api.currentElement.find('img');
            if ($img.length > 0 && $img.data('logged') !== 1 && ($img.attr('src').indexOf('action=useyourdrive-') === -1)) {
              var entry_id = $('a[href="' + $img.attr('src') + '"]').closest('[data-id]').data('id');
              $img.data('logged', 1);
              self._logEvent('log_preview_event', entry_id);
            }
          }
        },
        errors: {
          loadImage: self.options.str_imgError_title,
          loadContents: self.options.str_xhrError_title
        },
        text: {
          next: self.options.str_next_title,
          previous: self.options.str_previous_title,
          slideShow: self.options.str_startslideshow
        }
      });
    },

    /**
     * Create a direct URL to the entry
     * @param {Object} entry
     * @returns {String}
     */
    createUrlDeepLink: function (entry) {
      var hash_params = {
        'shortcode_id': this.options.listtoken,
        'request': null
      };
      var hash = Base64.encode(encodeURIComponent(JSON.stringify(hash_params)));
      return window.location.href.split("#").shift() + "#" + hash;
    },

    _logEvent: function (type, id) {
      var self = this;

      if (self.options.log_events === false) {
        return false;
      }

      $.ajax({type: "POST",
        url: self.options.ajax_url,
        data: {
          action: 'useyourdrive-event-stats',
          type: type,
          id: id,
          _ajax_nonce: self.options.log_nonce
        }
      });
    },

    _actionPreviewEntry: function (entry) {
      var self = this;
      $('.qtip').qtip('hide');
      var dataid = entry.closest("ul").attr('data-id');
      var link = self.element.find(".entry[data-id='" + dataid + "']").find(".entry_link")[0].click();
    },
    /**
     * Download an entry
     * @param {Object} entry
     * @param {string} mimetype
     */
    _actionDownloadEntry: function (entry) {
      var self = this;

      var dataname = entry.attr('data-filename');

      sendDriveGooglePageView('Download', dataname);

      var dataid = entry.closest("ul").attr('data-id');
      if (typeof dataid === 'undefined') {
        dataid = entry.closest(".entry").attr('data-id');
      }

      var $processor_icon = $('<div><i class="fas fa-cog fa-spin fa-1x fa-fw"></i></div>').css({'margin-right': '5px', 'display': 'inline-grid'}).delay(5000).fadeOut('slow', function () {
        $(this).remove();
      });
      self.element.find(".layout-grid .entry[data-id='" + dataid + "'] .entry-name-view").prepend($processor_icon);
      self.element.find(".layout-list .entry[data-id='" + dataid + "'] .entry_name").prepend($processor_icon);

      // Delay a few milliseconds for Tracking event
      setTimeout(function () {
        $('.qtip').qtip('hide');
        return true;
      }, 300);
    },

    /**
     * Export an entry
     * @param {Object} entry
     * @param {string} mimetype
     */
    _actionExportEntry: function (event, entry) {
      var self = this;

      event.stopPropagation();

      var href = entry.attr('href');
      var dataname = entry.attr('data-filename');

      sendDriveGooglePageView('Export', dataname);

      // Delay a few milliseconds for Tracking event
      setTimeout(function () {
        $('.qtip').qtip('hide');
        window.location = href;
        return true;
      }, 300);

      return false;
    },

    _actionShareEntry: function (entry) {
      var self = this;

      $('.qtip').qtip('hide');

      var dataid = entry.closest("ul").attr('data-id');
      var dataname = self.element.find(".entry[data-id='" + dataid + "']").attr('data-name');

      if (entry.hasClass('entry_action_shortlink_folder')) {
        var dataid = self.element.attr('data-id');
      }

      /* Close any open modal windows */
      $('#useyourdrive-modal-action').remove();

      /* Build the Delete Dialog */
      var modalbuttons = '';
      modalbuttons += '<button class="button useyourdrive-modal-confirm-btn" data-action="confirm" type="button" title="' + self.options.str_create_shared_link + '" >' + self.options.str_create_shared_link + '</button>';
      modalbuttons += '<button class="button useyourdrive-modal-cancel-btn" data-action="cancel" type="button" onclick="modal_action.close();" title="' + self.options.str_close_title + '" >' + self.options.str_close_title + '</button>';
      var modalheader = $('<a tabindex="0" class="close-button" title="' + self.options.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
      var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" ></div>');
      var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
      var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal ' + self.options.content_skin + '"><div class="modal-dialog"><div class="modal-content"></div></div></div>');
      $('body').append(modaldialog);
      $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

      $.ajax({type: "POST",
        url: self.options.ajax_url,
        data: {
          action: 'useyourdrive-create-link',
          listtoken: self.options.listtoken,
          id: dataid,
          _ajax_nonce: self.options.createlink_nonce
        },
        complete: function () {
          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').remove();
        },
        success: function (response) {
          if (response !== null) {
            if (response.link !== null) {
              $('.useyourdrive-modal-body').append('<input type="text" class="shared-link-url" value="' + response.link + '" style="width: 98%;" readonly/><div class="useyourdrive-shared-social"></div>');
              sendDriveGooglePageView('Create shared link');

              $.extend(jsSocials.shares, {
                directlink: {
                  label: "Link",
                  logo: "fas fa-link",
                  shareIn: "blank",
                  shareUrl: function () {
                    return response.link;
                  },
                  countUrl: ""
                }
              });

              $(".useyourdrive-shared-social").jsSocials({
                url: response.link,
                text: dataname + ' | ',
                showLabel: false,
                showCount: "inside",
                shareIn: "popup",
                shares: ["email", "twitter", "facebook", "googleplus", "linkedin", "pinterest", "whatsapp"]
              });

            } else {
              $('.useyourdrive-modal-body').find('.shared-link-url').val(response.error);
            }
          }
        },
        dataType: 'json'
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

      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').prop('disabled', true);
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').html('<i class="fas fa-cog fa-spin fa-fw"></i><span> ' + self.options.str_processing + '</span>');

      return false;
    },
    /**
     * Open a Dialog for creating a new Entry with a certain mimetype
     * @param {String} template_name
     * @param {String} mimetype
     */
    _actionCreateEntry: function (entry) {
      var self = this;

      $('.qtip').qtip('hide');

      /* Close any open modal windows */
      $('#useyourdrive-modal-action').remove();

      /* Build the Rename Dialog */
      var modalbuttons = '';
      modalbuttons += '<button class="button useyourdrive-modal-confirm-btn" data-action="rename" type="button" title="' + self.options.str_addfolder_title + '" >' + self.options.str_addfolder_title + '</button>';
      modalbuttons += '<button class="button useyourdrive-modal-cancel-btn" data-action="cancel" type="button" onclick="modal_action.close();" title="' + self.options.str_cancel_title + '" >' + self.options.str_cancel_title + '</button>';
      var addfolder_input = '<input type="text" id="useyourdrive-modal-addfolder-input" name="useyourdrive-modal-addfolder-input" value="" style="width:100%"/>';
      var modalheader = $('<a tabindex="0" class="close-button" title="' + self.options.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
      var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" >' + self.options.str_addfolder + ' <br/>' + addfolder_input + '</div>');
      var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
      var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal ' + self.options.content_skin + '"><div class="modal-dialog"><div class="modal-content"></div></div></div>');

      $('body').append(modaldialog);
      $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);
      /* Set the button actions */

      $('#useyourdrive-modal-action #useyourdrive-modal-addfolder-input').unbind('keyup');
      $('#useyourdrive-modal-action #useyourdrive-modal-addfolder-input').on("keyup", function (event) {
        if (event.which == 13 || event.keyCode == 13) {
          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').trigger('click');
        }
      });
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').unbind('click');
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').click(function () {

        var newinput = $('#useyourdrive-modal-addfolder-input').val();
        /* Check if there are illegal characters in the new name*/
        if (/[<>:"/\\|?*]/g.test($('#useyourdrive-modal-addfolder-input').val())) {
          $('#useyourdrive-modal-action .useyourdrive-modal-error').remove();
          $('#useyourdrive-modal-addfolder-input').after('<div class="useyourdrive-modal-error">' + self.options.str_rename_failed + '</div>');
          $('#useyourdrive-modal-action .useyourdrive-modal-error').fadeIn();
        } else {

          var data = {
            action: 'useyourdrive-add-folder',
            newfolder: encodeURIComponent(newinput),
            _ajax_nonce: self.options.addfolder_nonce
          };
          self._actionDoModifyEntry(data);

          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').prop('disabled', true);
          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').html('<i class="fas fa-cog fa-spin fa-fw"></i><span> ' + self.options.str_processing + '</span>');
        }

      });
      /* Open the dialog */
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
    },

    /**
     * Create a Dialog for renaming an entry
     * @param {Object} entry
     */
    _actionRenameEntry: function (entry) {

      var self = this;
      $('.qtip').qtip('hide');

      var dataid = entry.closest("ul").attr('data-id');
      var dataname = self.element.find(".entry[data-id='" + dataid + "']").attr('data-name');

      /* Close any open modal windows */
      $('#useyourdrive-modal-action').remove();

      /* Build the Rename Dialog */
      var modalbuttons = '';
      modalbuttons += '<button class="button useyourdrive-modal-confirm-btn" data-action="rename" type="button" title="' + self.options.str_rename_title + '" >' + self.options.str_rename_title + '</button>';
      modalbuttons += '<button class="button useyourdrive-modal-cancel-btn" data-action="cancel" type="button" onclick="modal_action.close();" title="' + self.options.str_cancel_title + '" >' + self.options.str_cancel_title + '</button>';
      var renameinput = '<input id="useyourdrive-modal-rename-input" name="useyourdrive-modal-rename-input" type="text" value="' + dataname + '" style="width:100%"/>';
      var modalheader = $('<a tabindex="0" class="close-button" title="' + self.options.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
      var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" >' + self.options.str_rename + '<br/>' + renameinput + '</div>');
      var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
      var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal ' + self.options.content_skin + '"><div class="modal-dialog"><div class="modal-content"></div></div></div>');

      $('body').append(modaldialog);
      $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);
      /* Set the button actions */

      $('#useyourdrive-modal-action #useyourdrive-modal-rename-input').unbind('keyup');
      $('#useyourdrive-modal-action #useyourdrive-modal-rename-input').on("keyup", function (event) {
        if (event.which == 13 || event.keyCode == 13) {
          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').trigger('click');
        }
      });
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').unbind('click');
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').click(function () {

        var new_filename = $('#useyourdrive-modal-rename-input').val();
        /* Check if there are illegal characters in the new name*/
        if (/[<>:"/\\|?*]/g.test($('#useyourdrive-modal-rename-input').val())) {
          $('#useyourdrive-modal-action .useyourdrive-modal-error').remove();
          $('#useyourdrive-modal-rename-input').after('<div class="useyourdrive-modal-error">' + self.options.str_rename_failed + '</div>');
          $('#useyourdrive-modal-action .useyourdrive-modal-error').fadeIn();
        } else {

          var data = {
            action: 'useyourdrive-rename-entry',
            id: dataid,
            newname: encodeURIComponent(new_filename),
            _ajax_nonce: self.options.rename_nonce
          };
          self._actionDoModifyEntry(data);

          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').prop('disabled', true);
          $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').html('<i class="fas fa-cog fa-spin fa-fw"></i><span> ' + self.options.str_processing + '</span>');
        }

      });
      /* Open the dialog */
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
    },

    /**
     * Create a Dialog for changing the description of an entry
     * @param {Object} entry
     */
    _actionEditDescriptionEntry: function (event, entry) {

      var self = this;

      var dataid = entry.attr('data-id');

      var descriptiondiv = entry.closest(".UseyourDrive").find('.description_text');
      var currentText = descriptiondiv.html();
      var editableText = $("<textarea />").addClass('description_textarea');
      editableText.val(currentText.replace(/<br\s?\/?>/g, "\n"));
      descriptiondiv.replaceWith(editableText);
      var loading = $('<img src="' + self.options.plugin_url + '/css/images/wpspin_light-2x.gif" width="16" height="16" />').addClass('ajaxprocess').hide();
      var savebutton = $('<input type="button" class="button" value="' + self.options.str_save_title + '"/>');
      editableText.after(loading).after(savebutton);
      editableText.focus();

      entry.hide();
      savebutton.click(function () {
        var newdescription = editableText.val();
        var viewableText = $("<div>").addClass('description_text');


        $.ajax({type: "POST",
          url: self.options.ajax_url,
          data: {
            action: 'useyourdrive-edit-description-entry',
            id: dataid,
            newdescription: encodeURIComponent(newdescription),
            listtoken: self.options.listtoken,
            _ajax_nonce: self.options.description_nonce
          },
          beforeSend: function () {
            savebutton.prop("disabled", true).fadeTo(400, 0.3);
            loading.show();
          },
          complete: function () {
            entry.show();
            savebutton.remove();
            loading.remove();
          },
          error: function () {
            viewableText.html(currentText);
            editableText.replaceWith(viewableText);
          },
          success: function (response) {
            if (response !== null) {
              if (typeof response.description !== 'undefined') {
                newdescription = response.description;
                viewableText.html(newdescription.replace(/\r\n|\r|\n/g, "<br />"));
                editableText.replaceWith(viewableText);
                return;
              }
            }
            viewableText.html(currentText);
            editableText.replaceWith(viewableText);
          },
          dataType: 'json'
        });
      });

      return false;
    },

    /**
     * Create a request to move the selected enties
     * @param {UI element} entry
     * @param {UI element} to_folder
     */
    _actionMoveEntry: function (entry, to_folder) {

      var data = {
        action: 'useyourdrive-move-entry',
        id: entry.attr('data-id'),
        copy: false,
        target: to_folder.attr('data-id'),
        _ajax_nonce: this.options.move_nonce
      };

      this._actionDoModifyEntry(data);

    },
    /**
     * Open a Dialog to delete selected entries
     * @param {Object} entries
     */
    _actionDeleteEntries: function (entries) {

      /* Close any open modal windows */
      $('.qtip').qtip('hide');
      $('#useyourdrive-modal-action').remove();

      /* Build the data request variable and make a list of the selected entries */
      var self = this, list_of_files = '', files = [];
      $.each(entries, function () {
        var $entry = $(this).closest('.entry');
        var $img = $entry.find('img:first()');

        var icon_tag = $('<div class="useyourdrive-modal-file-icon">');
        if ($img.length > 0) {
          $img.clone().appendTo(icon_tag);
        }
        list_of_files += '<li>' + icon_tag.html() + '<span>' + $entry.attr('data-name') + '</span></li>';
        files.push($(this).val());
      });

      /* Build the Delete Dialog */
      var modalbuttons = '';
      modalbuttons += '<button class="button useyourdrive-modal-confirm-btn" data-action="confirm" type="button" title="' + self.options.str_delete_title + '" >' + self.options.str_delete_title + '</button>';
      modalbuttons += '<button class="button useyourdrive-modal-cancel-btn" data-action="cancel" type="button" onclick="modal_action.close();" title="' + self.options.str_cancel_title + '" >' + self.options.str_cancel_title + '</button>';
      var modalheader = $('<a tabindex="0" class="close-button" title="' + self.options.str_close_title + '" onclick="modal_action.close();"><i class="fas fa-times fa-lg" aria-hidden="true"></i></a></div>');
      var modalbody = $('<div class="useyourdrive-modal-body" tabindex="0" >' + self.options.str_delete + '</br></br><ul class="files">' + list_of_files + '</ul></div>');
      var modalfooter = $('<div class="useyourdrive-modal-footer"><div class="useyourdrive-modal-buttons">' + modalbuttons + '</div></div>');
      var modaldialog = $('<div id="useyourdrive-modal-action" class="UseyourDrive useyourdrive-modal ' + self.options.content_skin + '"><div class="modal-dialog"><div class="modal-content"></div></div></div>');
      $('body').append(modaldialog);
      $('#useyourdrive-modal-action .modal-content').append(modalheader, modalbody, modalfooter);

      /* Set the button actions */
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').unbind('click');
      $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').click(function () {

        var data = {
          action: 'useyourdrive-delete-entries',
          entries: files,
          _ajax_nonce: self.options.delete_nonce
        };
        self._actionDoModifyEntry(data);

        $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').prop('disabled', true);
        $('#useyourdrive-modal-action .useyourdrive-modal-confirm-btn').html('<i class="fas fa-cog fa-spin fa-fw"></i><span> ' + self.options.str_processing + '</span>');
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
    },
    _actionDoModifyEntry: function (request) {
      var self = this;
      var lastFolder = self.element.attr('data-id');

      request.listtoken = this.options.listtoken;
      request.lastFolder = lastFolder;

      $.ajax({
        type: "POST",
        url: self.options.ajax_url,
        data: request,
        beforeSend: function () {
          self.options.loadingContainer.fadeIn(400);
        },
        success: function (json) {

          if (typeof json !== 'undefined') {
            if (typeof json.lastFolder !== 'undefined' && (json.lastFolder !== null)) {
              self.element.attr('data-id', json.lastFolder);
            }
          }

        },
        complete: function () {

          if (typeof modal_action !== 'undefined') {
            modal_action.close();
          }
          if (typeof modal !== 'undefined') {
            modal.close();
          }

          self.options.forceRefresh = true;
          self._getFileList({});
        },
        dataType: 'json'
      });
    },

    /* ***** Helper functions for File Upload ***** */
    /* Validate File for Upload */
    _uploadValidateFile: function (file, position) {
      var self = this;


      var maxFileSize = self.element.find('input[name="maxfilesize"]').val();
      var acceptFileType = new RegExp(self.element.find('input[name="acceptfiletypes"]').val(), "i");

      file.error = false;
      if (file.name.length && !acceptFileType.test(file.name)) {
        file.error = self.options.acceptFileTypes;
      }
      if (maxFileSize !== '' && file.size > 0 && file.size > maxFileSize) {
        file.error = self.options.maxFileSize;
      }

      if (self.number_of_uploaded_files.Max > 0 && (self.number_of_uploaded_files.Counter > self.number_of_uploaded_files.Max)) {
        var max_reached = true;
        /* Allow upload of the same file */
        $.each(self.uploaded_files_storage, function () {
          if (this.name === file.name) {
            max_reached = false;
            self.number_of_uploaded_files.Counter--; // Don't count this as an extra file
          }
        });

        if (max_reached) {
          file.error = self.options.maxNumberOfFiles;
        }
      }

      return file;
    },

    /* Get Progress for uploading files to cloud*/
    _uploadGetProgress: function (file) {
      var self = this;

      $.ajax({type: "POST",
        url: self.options.ajax_url,
        data: {
          action: 'useyourdrive-upload-file',
          type: 'get-status',
          listtoken: self.options.listtoken,
          hash: file.hash,
          _ajax_nonce: self.options.upload_nonce
        },
        dataType: 'json',
        success: function (response) {
          if (response !== null) {
            if (typeof response.status !== 'undefined') {
              if (response.status.progress === 'starting' || response.status.progress === 'uploading') {
                setTimeout(function () {
                  self._uploadGetProgress(response.file);
                }, 1500);
              }
              self._uploadRenderRowOnProgress(response.file, {percentage: 50 + (response.status.percentage / 2), progress: response.status.progress});
            } else {
              file.error = self.options.str_error;
              self._uploadRenderRowOnFinish(file);
            }
          }
        },
        error: function (response) {
          file.error = self.options.str_error;
          self._uploadRenderRowOnFinish(file);
        }
      });

    },

    /* Render file in upload list */
    _uploadRenderRow: function (file) {
      var self = this;

      var row = self.element.find('.template-row').clone().removeClass('template-row');
      var cancel_button = $('<a class="cancel-upload"><i class="fas fa-ban" aria-hidden="true"></i> ' + self.options.str_delete_title + '</a>');

      row.attr('data-file', file.name).attr('data-id', file.hash);
      row.find('.file-name').text(file.name);
      if (file.size !== 'undefined' && file.size > 0) {
        row.find('.file-size').text(self._helperFormatBytes(file.size, 1));
      }
      row.find('.upload-thumbnail img').attr('src', self._uploadGetThumbnail(file));

      row.addClass('template-upload');
      row.find('.upload-status').removeClass().addClass('upload-status queue').append(cancel_button);
      row.find('.upload-status-icon').removeClass().addClass('upload-status-icon fas fa-circle').hide();

      self.element.find('.fileupload-list .files').append(row);
      self.element.find('div.fileupload-drag-drop').fadeOut();

      if (typeof file.error !== 'undefined' && file.error !== false) {
        self._uploadRenderRowOnFinish(file);
      }

      return row;

    },
    _uploadRenderRowOnStart: function (file) {
      var self = this;

      var row = self.element.find(".fileupload-list [data-id='" + file.hash + "']");

      row.find('.upload-status').removeClass().addClass('upload-status succes').text(self.options.str_uploading_local);
      row.find('.upload-status-icon').removeClass().addClass('upload-status-icon fas fa-circle-o-notch fa-spin').fadeIn();
      row.find('.upload-progress').slideDown();
      $('input[type="submit"]').prop('disabled', true).fadeTo(400, 0.3);

    },

    /* Render the progress of uploading cloud files */
    _uploadRenderRowOnProgress: function (file, status) {
      var self = this;

      var row = self.element.find(".fileupload-list [data-id='" + file.hash + "']");
      var progress_bar = row.find('.ui-progressbar');
      var progress_bar_value = progress_bar.find('.ui-progressbar-value');

      progress_bar_value.fadeIn().animate({
        width: (status.percentage / 100) * progress_bar.width()
      }, 50);

      if (status.progress === 'uploading_to_cloud') {
        row.find('.upload-status').text(self.options.str_uploading_cloud);
      }
    },

    _uploadRenderRowOnFinish: function (file) {
      var self = this;

      var row = self.element.find(".fileupload-list [data-id='" + file.hash + "']");

      row.addClass('template-download').removeClass('template-upload');
      row.find('.file-name').text(file.name);
      row.find('.upload-thumbnail img').attr('src', self._uploadGetThumbnail(file));
      row.find('.upload-progress').slideUp();

      if (typeof file.error !== 'undefined' && file.error !== false) {
        row.find('.upload-status').removeClass().addClass('upload-status error').text(self.options.str_error);
        row.find('.upload-status-icon').removeClass().addClass('upload-status-icon fas fa-exclamation-circle');
        row.find('.upload-error').text(file.error).slideUp().delay(500).slideDown();
        self.number_of_uploaded_files.Counter--;
      } else {
        row.find('.upload-status').removeClass().addClass('upload-status succes').text(self.options.str_success);
        row.find('.upload-status-icon').removeClass().addClass('upload-status-icon fas fa-check-circle');

        self.uploaded_files.push(file.fileid);
      }

      if (self.element.find('.template-upload').length < 1) {
        clearTimeout(self.uploadPostProcessTimer);
        self.uploadPostProcessTimer = setTimeout(function () {
          self._uploadDoPostProcess();
        }, 500);
      }

      if (row.closest('.gform_wrapper').length > 0 || row.closest('.wpcf7').length > 0 || (self.element.hasClass('upload') === true)) {
        /* Keep the upload listed in Forms */
      } else {
        self._uploadDeleteRow(file, 5000);
      }
    },

    _uploadDeleteRow: function (file, delayms) {
      var self = this;

      var row = self.element.find(".fileupload-list [data-id='" + file.hash + "']");

      row.delay(delayms).animate({"opacity": "0"}, "slow", function () {
        $(this).remove();

        if (self.element.find('.template-upload').length < 1) {
          self.element.find('div.fileupload-drag-drop').fadeIn();
        }
      });
    },

    _uploadDoRequest: function (data) {
      var self = this;

      if ($.active === 0) {
        data.submit();
      } else {
        window.setTimeout(function () {
          self._uploadDoRequest(data)
        }, 200);
      }
    },

    _uploadDoConvert: function (file) {
      var self = this;

      var row = self.element.find(".fileupload-list [data-id='" + file.hash + "']");

      $.ajax({type: "POST",
        url: UseyourDrive_vars.ajax_url,
        data: {
          action: 'useyourdrive-upload-file',
          type: 'upload-convert',
          listtoken: self.options.listtoken,
          fileid: file.fileid,
          convert: file.convert,
          _ajax_nonce: self.options.upload_nonce
        },
        success: function (response) {
          if (response !== null) {
            if (typeof response.result === 'undefined' || response.result === 0) {
              file.error = self.options.str_error;
            } else {
              file.fileid = response.fileid;
            }
          } else {
            file.error = self.options.str_error;
          }
        },
        error: function (response) {
          file.error = self.options.str_error;
        },
        complete: function (response) {
          self._uploadRenderRowOnFinish(file);
        },
        dataType: 'json'
      });

    },
    /* Upload Notification function to send notifications if needed after upload */
    _uploadDoPostProcess: function () {
      var self = this;

      $.ajax({type: "POST",
        url: self.options.ajax_url,
        data: {
          action: 'useyourdrive-upload-file',
          type: 'upload-postprocess',
          listtoken: self.options.listtoken,
          files: self.uploaded_files,
          _ajax_nonce: self.options.upload_nonce
        },
        success: function (response) {
          if (response !== null) {
            self.uploaded_files = [];

            $.each(response.files, function (fileid, file) {
              self.uploaded_files_storage[fileid] = {
                "hash": fileid,
                "name": file.name,
                "type": file.type,
                "path": file.completepath,
                "size": file.filesize,
                "link": file.link,
                "folderurl": file.folderurl,
              };
            });

            self.element.closest('form').find('.fileupload-filelist').val(JSON.stringify(self.uploaded_files_storage));
          }
        },
        complete: function (response) {

          if (self.element.hasClass('upload') === false) {
            self.options.clearLocalCache = true;

            clearTimeout(self.updateTimer);
            self._getFileList({});
          }

          if (self.element.find('.fileupload-filelist').find('.template-upload').length < 1) {
            /* Remove navigation prompt */
            window.onbeforeunload = null;

            self.element.find('.fileuploadform').trigger('useyourdrive-upload-finished');
          }
        },
        dataType: 'json'
      });
    },

    _uploadGetThumbnail: function (file) {
      var self = this;

      var thumbnailUrl = self.options.icons_set;
      if (typeof file.thumbnail === 'undefined' || file.thumbnail === null || file.thumbnail === '') {
        var icon;

        if (typeof file.type === 'undefined' || file.type === null) {
          icon = 'icon_11_generic_xl128';
        } else if (file.type.indexOf("word") >= 0) {
          icon = 'icon_11_word_xl128';
        } else if (file.type.indexOf("excel") >= 0 || file.type.indexOf("spreadsheet") >= 0) {
          icon = 'icon_11_excel_xl128';
        } else if (file.type.indexOf("powerpoint") >= 0 || file.type.indexOf("presentation") >= 0) {
          icon = 'icon_11_powerpoint_xl128';
        } else if (file.type.indexOf("image") >= 0) {
          icon = 'icon_11_image_xl128';
        } else if (file.type.indexOf("audio") >= 0) {
          icon = 'icon_11_audio_xl128';
        } else if (file.type.indexOf("video") >= 0) {
          icon = 'icon_11_video_xl128';
        } else if (file.type.indexOf("pdf") >= 0) {
          icon = 'icon_11_pdf_xl128';
        } else if (file.type.indexOf("text") >= 0) {
          icon = 'icon_11_text_xl128';
        } else {
          icon = 'icon_11_generic_xl128';
        }
        return thumbnailUrl + icon + '.png';
      } else {
        return file.thumbnail;
      }
    },

    _initDragDrop: function () {
      var self = this;
      $(document).bind('dragover', function (e) {
        var dropZone = self.element,
                timeout = window.dropZoneTimeout;
        if (!timeout) {
          dropZone.addClass('in');
        } else {
          clearTimeout(timeout);
        }
        var found = false, node = e.target;
        do {
          if ($(node).is(dropZone)) {
            found = true;
            break;
          }
          node = node.parentNode;
        } while (node !== null);
        if (found) {
          $(node).addClass('hover');
        } else {
          dropZone.removeClass('hover');
        }
        window.dropZoneTimeout = setTimeout(function () {
          window.dropZoneTimeout = null;
          dropZone.removeClass('in hover');
        }, 100);
      });
      $(document).bind('drop dragover', function (e) {
        e.preventDefault();
      });
    },

    _initResizeHandler: function () {
      var self = this;
      self._orgininal_width = self.element.width();

      $(window).resize(function () {

        if (self._orgininal_width === self.element.width()) {
          return;
        }

        self._orgininal_width = self.element.width();

        self._refreshView();
      });
    },

    _refreshView: function () {
      var self = this;

      self.element.find('.jp-jplayer').each(function () {

        if (typeof $(this).data().jPlayer !== 'undefined') {
          var status = ($(this).data().jPlayer.status);
          if (status.videoHeight !== 0 && status.videoWidth !== 0) {
            var ratio = status.videoWidth / status.videoHeight;
            var jpvideo = $(this);
            if ($(this).find('object').length > 0) {
              var jpobject = $(this).find('object');
            } else {
              var jpobject = $(this).find('video');
            }

            if (jpvideo.height() !== jpvideo.width() / ratio) {
              if ((screen.height >= (jpvideo.width() / ratio)) || (status.cssClass !== "jp-video-full")) {
                jpobject.height(jpobject.width() / ratio);
                jpvideo.height(jpobject.width() / ratio);
              } else {
                jpobject.width(screen.height * ratio);
                jpvideo.width(screen.height * ratio);
              }
            }
            $(this).parent().find(".jp-video-play").height(jpvideo.height());
          }
        }
      });

      // set a timer to re-apply the plugin
      if (typeof self.resizeTimer !== 'undefined') {
        clearTimeout(self.resizeTimer);
      }

      self.element.find('.image-collage').fadeTo(100, 0);
      self.element.find('.layout-grid').fadeTo(100, 0);

      self.resizeTimer = setTimeout(function () {
        if (self.options.topContainer.hasClass('files') || self.options.topContainer.hasClass('search')) {
          self.renderContentForBrowser();
        }

        if (self.options.topContainer.hasClass('gallery')) {
          self.renderContentForGallery();
        }
      }, 100);
    },

    /**
     * Pipelining function to cache ajax requests
     */
    _pipeline: function (opts) {
      var self = this;
      var conf = $.extend({
        url: self.options.ajax_url,
        data: null,
        method: 'POST'
      }, opts);

      return function (request, drawCallback, settings) {

        var d = conf.data(request);
        $.extend(request, d);
        var storage_id = (request.listtoken + request._ajax_nonce + request.filelayout + (typeof request.id === 'undefined' ? request.lastFolder : request.id) + request.sort + request.query);
        var storage_key = 'CloudPlugin_' + storage_id.hashCode();

        if (self.options.clearLocalCache) {
          self._cacheRemove('all');
          self.options.clearLocalCache = false;
        }

        // API request that the cache be cleared
        if (self.options.forceRefresh) {
          self._cacheRemove('all');
          request.hardrefresh = true;
          self.options.forceRefresh = false;
        }

        if (self._cacheGet(storage_key) !== null) {
          var json = self._cacheGet(storage_key);
          json.draw = request.draw; // Update the echo for each response
          drawCallback(self, json);
          return true;
        }

        if (typeof settings.jqXHR !== 'undefined' && settings.jqXHR !== null) {
          settings.jqXHR.abort();
        }

        settings.jqXHR = $.ajax({
          type: conf.method,
          url: conf.url,
          data: request,
          dataType: "json",
          cache: false,
          beforeSend: function () {

          },
          success: function (json) {

            if (json === null && json === 0) {
              self.element.trigger('ajax-error', [json, request, settings.jqXHR]);
              drawCallback(self, false);
              return false;
            }

            self.element.trigger('ajax-success', [json, request, settings.jqXHR]);
            self._cacheSet(storage_key, json);
            drawCallback(self, json);
          },
          error: function (json) {
            self.element.trigger('ajax-error', [json, request, settings.jqXHR]);
            drawCallback(self, false);
            return false;

          }
        });

      };
    },

    _initCache: function () {
      var self = this;

      self._isCacheStorageAvailable = self._cacheStorageAvailable();
      setInterval(function () {
        self._cacheRemove('all');
      }, 1000 * 60 * 15);
    },

    _cacheStorageAvailable: function () {

      try {
        var storage = window['sessionStorage'],
                x = '__storage_test__';
        storage.setItem(x, x);
        storage.removeItem(x);
        return true;
      } catch (e) {
        return e instanceof DOMException && (
                // everything except Firefox
                e.code === 22 ||
                // Firefox
                e.code === 1014 ||
                // test name field too, because code might not be present
                // everything except Firefox
                e.name === 'QuotaExceededError' ||
                // Firefox
                e.name === 'NS_ERROR_DOM_QUOTA_REACHED') &&
                // acknowledge QuotaExceededError only if there's something already stored
                storage.length !== 0;
      }
    },

    _cacheGet: function (key) {
      if (typeof this.cache.expires === 'undefined') {
        var expires = new Date();
        expires.setMinutes(expires.getMinutes() + 15);
        this.cache.expires = expires;
      }

      if (this.cache.expires.getTime() < new Date().getTime()) {
        this._cacheRemove(key);
      }

      if (this._isCacheStorageAvailable) {
        return JSON.parse(sessionStorage.getItem(key));
      } else {

        if (typeof this.cache[key] === 'undefined') {
          return null;
        }

        return this.cache[key];
      }

    },
    _cacheSet: function (key, value) {
      if (this._isCacheStorageAvailable) {
        try {
          sessionStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
          this._cacheRemove('all');
          return false;
        }
      } else {
        if (typeof this.cache[key] === 'undefined') {
          this.cache[key] = {};
        }

        this.cache[key] = value;
      }
    },
    _cacheRemove: function (key) {
      if (this._isCacheStorageAvailable) {

        if (key === 'all') {
          var i = sessionStorage.length;
          while (i--) {
            var key = sessionStorage.key(i);
            if (/CloudPlugin/.test(key)) {
              sessionStorage.removeItem(key);
            }
          }
        } else {
          sessionStorage.removeItem(key);
        }

      } else {

        if (key === 'all') {
          delete this.cache;
        } else {
          delete this.cache[key];
        }

      }
    },

    _helperDownloadUrlInline: function (url) {
      var hiddenIFrameID = 'hiddenDownloader';
      var iframe = document.getElementById(hiddenIFrameID);
      if (iframe === null) {
        iframe = document.createElement('iframe');
        iframe.id = hiddenIFrameID;
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
      }
      iframe.src = url;
    },
    _helperFormatBytes: function (bytes, decimals) {
      if (bytes == 0)
        return '—';
      var k = 1000; // or 1024 for binary
      var dm = decimals + 1 || 3;
      var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
    _helperIframeFix: function ($element) {
      /* Safari bug fix for embedded iframes*/
      if (/iPhone|iPod|iPad/.test(navigator.userAgent)) {
        $element.each(function () {
          if ($(this).closest('#safari_fix').length === 0) {
            $(this).wrap(function () {
              return $('<div id="safari_fix"/>').css({
                'width': "100%",
                'height': "100%",
                'overflow': 'auto',
                'z-index': '2',
                '-webkit-overflow-scrolling': 'touch'
              });
            });
          }
        });
      }
    },
    _helperCachedScript: function (url, options) {

      // Allow user to set any option except for dataType, cache, and url
      options = jQuery.extend(options || {}, {
        dataType: "script",
        cache: true,
        url: url
      });

      // Use $.ajax() since it is more flexible than $.getScript
      // Return the jqXHR object so we can chain callbacks
      return jQuery.ajax(options);
    },
    _helperReadArrCheckBoxes: function (element) {
      var values = $(element + ":checked").map(function () {
        return this.value;
      }).get();

      return values;
    },
    _helperIsIE: function () {
      var myNav = navigator.userAgent.toLowerCase();
      return (myNav.indexOf('msie') != -1) ? parseInt(myNav.split('msie')[1]) : false;
    }
  });
})(jQuery);

(function ($) {
  $(".UseyourDrive").UseyourDrive(UseyourDrive_vars);
})(jQuery)

var uyd_playlists = {};

function sendDriveGooglePageView(action, value) {
  if (UseyourDrive_vars.google_analytics === "1") {
    if (typeof ga !== "undefined" && ga !== null) {
      ga('send', 'event', 'Use-your-Drive', action, value);
    }

    if (typeof _gaq !== "undefined" && _gaq !== null) {
      _gaq.push(['_trackEvent', 'Use-your-Drive', action, value]);
    }

    if (typeof gtag !== "undefined" && gtag !== null) {
      gtag('event', action, {
        'event_category': 'Use-your-Drive',
        'event_label': value,
        'value': value
      });
    }
  }
}