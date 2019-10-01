 //Masonry

jQuery(document).ready(function($){


var $container = $('.masonry').imagesLoaded( function() {
//var $container = $('.masonry');
  $container.imagesLoaded(function(){
    $container.masonry({
    // options
    //columnWidth: '.grid-sizer',
    itemSelector: '.grid-item',
    percentPosition: true,
    //columnWidth: 200
    });
  });
});


  $('#cssmenu').prepend('<div id="menu-button">Menu</div>');
  $('#cssmenu #menu-button').on('click', function(){
    var menu = $(this).next('ul');
    if (menu.hasClass('open')) {
      menu.removeClass('open');
    } else {
      menu.addClass('open');
    }
  });



function addBlacklistClass() {
    $( 'a' ).each( function() {
        if ( this.href.indexOf('/wp-admin/') !== -1 || 
             this.href.indexOf('/wp-login.php') !== -1 ) {
            $( this ).addClass( 'wp-link' );
        }
    });
}

function addMenu() {
  $('#menu-icon').click(function(){
    //this is for the burger menu
    $(this).toggleClass('open');
    //this toggles the height
    var theHeight = $(window).height();
      if($('#menu-wrapper').hasClass('show')) {
        
        $('#menu-wrapper').animate({height:0},500).removeClass('show');

      } else { 

        $('#menu-wrapper').animate({height:theHeight,display:"block"},500).addClass('show');
      }

  });
} 
$( function() {
 
    addBlacklistClass();
    addMenu();
    
    var settings = { 
        anchors: 'a',
        blacklist: '.wp-link, .wp-link a',
        onStart: {
          duration: 280, // ms
            render: function ( $container ) {
                $container.addClass( 'slide-out' );
            }
        },
        onAfter: function( $container ) {
            addBlacklistClass();
            addMenu();
            $container.removeClass( 'slide-out' );

            var $hash = $( window.location.hash );

            if ( $hash.length !== 0 ) {

                var offsetTop = $hash.offset().top;

                $( 'body, html' ).animate( {
                        scrollTop: ( offsetTop - 60 ),
                    }, {
                        duration: 280
                } );
            }


        }
    };
 
    $( '#page' ).smoothState( settings );
} );
});


