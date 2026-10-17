/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.yoursite = {
    attach: function (context, settings) {

        // Make submenu full browser width.
        const submenuFullwidthCalc = function () {
          // Get the Mega menu Level 1 sub menu.
          $(".tbm-nav > .level-1 > .tbm-submenu").each(function () {
            // reset to zero so it can be calculated again and again
            $(this).css("left", 0);
            const offsettarget = $("body").offset();
            // The offset of this submenu.
            const offsetthis = $(this)
              //.parent()
              .parents( "ul" ) // get parent ul instead
              .offset();
            // Calculate the offset.
            $(this).css("left", offsettarget.left - offsetthis.left);

            // Set the submenu full width.
            $(this).css("width", $("body").width());
          });
        };

        $(window).on("load resize", function () {
          submenuFullwidthCalc();
        });

    }



  };

})(jQuery, Drupal);