function initate_use_your_drive_media() {
  jQuery(function ($) {
    'use strict';

    /* Audio Players*/
    $('.UseyourDrive.media.audio').each(function () {
      var listtoken = $(this).attr('data-token'),
              extensions = $(this).attr('data-extensions'),
              autoplay = $(this).attr('data-autoplay'),
              jPlayerSelector = '#' + $(this).find('.jp-jplayer').attr('id'),
              cssSelector = '#' + $(this).find('.jp-video').attr('id');
      uyd_playlists[listtoken] = new jPlayerPlaylist({
        jPlayer: jPlayerSelector,
        cssSelectorAncestor: cssSelector
      }, [], {
        playlistOptions: {
          autoPlay: (autoplay === '1' ? true : false)
        },
        swfPath: UseyourDrive_vars.js_url,
        supplied: extensions,
        solution: "html,flash",
        wmode: "window",
        size: {
          width: "100%",
          height: "0px"
        },
        ready: function () {
          var data = {
            action: 'useyourdrive-get-playlist',
            lastFolder: $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-id'),
            sort: $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-sort'),
            listtoken: listtoken,
            _ajax_nonce: UseyourDrive_vars.getplaylist_nonce
          };
          $.ajax({
            type: "POST",
            url: UseyourDrive_vars.ajax_url,
            data: data,
            success: function (result) {
              if (result instanceof Array) {
                uyd_playlists[listtoken].setPlaylist(result);

                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").css('opacity', '100');

                if (!$(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist").hasClass('hideonstart')) {
                  $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist").slideDown("slow");
                }

                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-dl").unbind('click');
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-dl").click(function (e) {
                  e.stopPropagation();
                  var href = $(this).attr('href') + '&dl=1',
                          dataname = $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-title").html() +
                          " - " + $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-artist").html();

                  sendDriveGooglePageView('Download', dataname);

                  // Delay a few milliseconds for Tracking event
                  setTimeout(function () {
                    window.location = href;
                  }, 300);

                  return false;

                });

                switchSong(listtoken);
              } else {
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-title").html(UseyourDrive_vars.str_error);
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-artist").html(UseyourDrive_vars.str_xhrError_title);
                $("#UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").fadeOut();
              }
            },
            error: function () {
              $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-title").html(UseyourDrive_vars.str_error);
              $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-artist").html(UseyourDrive_vars.str_xhrError_title);
              $("#UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").fadeOut();
            },
            dataType: 'json'
          });
          $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer img").imagesLoaded(function () {
            $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").stop().delay(1500).animate({height: "200px"});
          });

        },
        play: function (e) {
          var dataname = $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-title").html() +
                  " - " + $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-artist").html();
          switchSong(listtoken);
          sendDriveGooglePageView('Play Music', dataname);
        },
        loadstart: function (e) {
          $(".UseyourDrive[data-token='" + listtoken + "']").find('.jp-song-title .jp-playlist-item-song-artist').html('<i class="fas fa-circle-notch fa-spin fa-fw"></i>');
        },
        loadedmetadata: function (e) {
          var $song_artist = $(".UseyourDrive[data-token='" + listtoken + "']").find('.jp-playlist ul li.jp-playlist-current .jp-playlist-current .jp-playlist-item-song-artist').html();
          $(".UseyourDrive[data-token='" + listtoken + "']").find('.jp-song-title .jp-playlist-item-song-artist').html($song_artist);
        }
      });
    });


    /* Video Players*/
    $('.UseyourDrive.media.video').each(function () {
      var listtoken = $(this).attr('data-token'),
              extensions = $(this).attr('data-extensions'),
              autoplay = $(this).attr('data-autoplay'),
              jPlayerSelector = '#' + $(this).find('.jp-jplayer').attr('id'),
              cssSelector = '#' + $(this).find('.jp-video').attr('id');
      uyd_playlists[listtoken] = new jPlayerPlaylist({
        jPlayer: jPlayerSelector,
        cssSelectorAncestor: cssSelector
      }, [], {
        playlistOptions: {
          autoPlay: (autoplay === '1' ? true : false)
        },
        swfPath: UseyourDrive_vars.js_url,
        supplied: extensions,
        solution: "html,flash",
        audioFullScreen: true,
        errorAlerts: false,
        warningAlerts: false,
        size: {
          width: "100%",
          height: "100%"
        },
        ready: function (e) {
          var data = {
            action: 'useyourdrive-get-playlist',
            lastFolder: $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-id'),
            sort: $(".UseyourDrive[data-token='" + listtoken + "']").attr('data-sort'),
            listtoken: listtoken,
            _ajax_nonce: UseyourDrive_vars.getplaylist_nonce
          };
          $.ajax({
            type: "POST",
            url: UseyourDrive_vars.ajax_url,
            data: data,
            success: function (result) {
              if (result instanceof Array) {
                uyd_playlists[listtoken].setPlaylist(result);

                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").css('opacity', '100');

                if (!$(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist").hasClass('hideonstart')) {
                  $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist").slideDown("slow");
                }
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-dl").unbind('click');
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-dl").click(function (e) {
                  e.stopPropagation();
                  var href = $(this).attr('href') + '&dl=1',
                          dataname = $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-title").html() +
                          " - " + $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-artist").html();

                  sendDriveGooglePageView('Download', dataname);

                  // Delay a few milliseconds for Tracking event
                  setTimeout(function () {
                    window.location = href;
                  }, 300);

                  return false;

                });
              } else {
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-title").html(UseyourDrive_vars.str_error);
                $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-artist").html(UseyourDrive_vars.str_xhrError_title);
                $("#UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").fadeOut();
              }
              switchSong(listtoken);
            },
            error: function () {
              $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-title").html(UseyourDrive_vars.str_error);
              $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item-song-artist").html(UseyourDrive_vars.str_xhrError_title);
              $("#UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").fadeOut();
            },
            dataType: 'json'
          });
          $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").height($(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").width() / 1.6);
          $(".UseyourDrive[data-token='" + listtoken + "'] object").width('100%');
          $(".UseyourDrive[data-token='" + listtoken + "'] object").height($(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").height());
        },
        ended: function (e) {

        },
        pause: function (e) {
          $(".UseyourDrive[data-token='" + listtoken + "'] .jp-video-play").height($(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").height());
        },
        loadedmetadata: function (e) {

          if (e.jPlayer.status.videoHeight !== 0 && e.jPlayer.status.videoWidth !== 0) {
            var ratio = e.jPlayer.status.videoWidth / e.jPlayer.status.videoHeight;
            var videoselector = $(".UseyourDrive[data-token='" + listtoken + "'] object");
            if (e.jPlayer.html.active === true) {
              videoselector = $(".UseyourDrive[data-token='" + listtoken + "'] video");
              videoselector.bind('contextmenu', function () {
                return false;
              });
            }
            if (videoselector.height() === 0 || videoselector.height() !== videoselector.parent().width() / ratio) {
              videoselector.width(videoselector.parent().width());
              videoselector.height(videoselector.width() / ratio);
              $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer").animate({height: (videoselector.width() / ratio)});
            }
            $(".UseyourDrive[data-token='" + listtoken + "'] .jp-jplayer img").hide();
          }
        },
        waiting: function (e) {
          var videoselector = $(".UseyourDrive[data-token='" + listtoken + "'] object");
          if (e.jPlayer.html.active === true) {
            videoselector = $(".UseyourDrive[data-token='" + listtoken + "'] video");
            videoselector.bind('contextmenu', function () {
              return false;
            });
          }
        },
        resize: function (e) {
        },
        play: function (e) {
          var dataname = $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-title").html() +
                  " - " + $(".UseyourDrive[data-token='" + listtoken + "'] .jp-playlist-item.jp-playlist-current  .jp-playlist-item-song-artist").html();
          sendDriveGooglePageView('Play Video', dataname);
          switchSong(listtoken);

        }
      });
    });

    function switchSong(listtoken) {
      var $this = $(".UseyourDrive[data-token='" + listtoken + "'].media");

      $this.find(".jp-previous").removeClass('disabled');
      $this.find(".jp-next").removeClass('disabled');

      if (($this.find('.jp-playlist ul li:last-child')).hasClass('jp-playlist-current')) {
        $this.find(".jp-next").addClass('disabled');
      }

      if (($this.find('.jp-playlist ul li:first-child')).hasClass('jp-playlist-current')) {
        $this.find(".jp-previous").addClass('disabled');
      }

      var $song_title = $this.find('.jp-playlist ul li.jp-playlist-current .jp-playlist-current').html();
      $this.find('.jp-song-title').html($song_title);
    }

    $(".UseyourDrive .jp-playlist-toggle").unbind('click').click(function () {
      var $this = $(this).closest('.media');
      $this.find(".jp-playlist").slideToggle("slow");
    });

  });
};
initate_use_your_drive_media();