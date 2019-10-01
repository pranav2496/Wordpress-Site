jQuery(function ($) {
  var wc_useyourdrive = {
    // hold a reference to the last selected Google Drive button
    lastSelectedButton: false,

    init: function () {
      // add button for simple product
      this.addButtons();
      this.addButtonEventHandler();
      // add buttons when variable product added
      $('#variable_product_options').on('woocommerce_variations_added', function () {
        wc_useyourdrive.addButtons();
        wc_useyourdrive.addButtonEventHandler();
      });
      // add buttons when variable products loaded
      $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        wc_useyourdrive.addButtons();
        wc_useyourdrive.addButtonEventHandler();
      });

      return this;
    },

    addButtons: function () {
      var button = $('<a href="#TB_inline?height=450&amp;width=800&amp;inlineId=uyd-embedded" class="button insert-googledrive thickbox">' + useyourdrive_woocommerce_translation.choose_from_googledrive + '</a>');
      $('.downloadable_files').each(function (index) {

        // we want our button to appear next to the insert button
        var insertButton = $(this).find('a.button.insert');
        // check if googledrive button already exists on element, bail if so
        if ($(this).find('a.button.insert-googledrive').length) {
          return;
        }

        // finally clone the button to the right place
        var plugin_button = insertButton.after(button.clone());

      });
    },
    /**
     * Adds the click event to the dropbox buttons
     * and opens the Google Drive chooser
     */
    addButtonEventHandler: function () {
      $('a.button.insert-googledrive').on('click', function (e) {
        e.preventDefault();

        // save a reference to clicked button
        wc_useyourdrive.lastSelectedButton = $(this);

      });
    },
    /**
     * Handle selected files
     */
    afterFileSelected: function (id, name) {
      var table = $(wc_useyourdrive.lastSelectedButton).closest('.downloadable_files').find('tbody');
      var template = $(wc_useyourdrive.lastSelectedButton).parent().find('.button.insert:first').data("row");
      var fileRow = $(template);

      fileRow.find('.file_name > input:first').val(name).change();
      fileRow.find('.file_url > input:first').val(useyourdrive_woocommerce_translation.download_url + id);
      table.append(fileRow);

      // trigger change event so we can save variation
      $(table).find('input').last().change();
    }

  };
  window.wc_useyourdrive = wc_useyourdrive.init();
});



