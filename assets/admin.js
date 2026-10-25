jQuery(function ($) {
  const form = $('#wcpsp-form');
  const presetIdField = $('#wcpsp_preset_id');
  const presetNameField = $('#wcpsp_preset_name');
  const sortTypeField = $('#wcpsp_sort_type');
  const orderField = $('#wcpsp_order');
  const metaKeyField = $('#wcpsp_meta_key');
  const metaTypeField = $('#wcpsp_meta_type');
  const listWrap = $('#wcpsp_presets_list');
  const logContainer = $('#wcpsp_log_container');
  const submitBtn = $('#wcpsp_submit');
  const cancelBtn = $('#wcpsp_cancel_edit');
  const refreshBtn = $('#wcpsp-refresh-log');
  const clearBtn = $('#wcpsp-clear-log');

  function toggleMetaFields() {
    const isCustom = sortTypeField.val() === 'custom_meta';
    $('#wcpsp_custom_meta_row').toggle(isCustom);
    $('#wcpsp_meta_type_row').toggle(isCustom);
  }

  function resetForm() {
    form[0].reset();
    presetIdField.val('');
    submitBtn.text('Save preset');
    cancelBtn.hide();
    toggleMetaFields();
  }

  function refreshLogViewer() {
    $.post(wcpsp.ajax_url, {
      action: 'wcpsp_get_log',
      nonce: wcpsp.nonce
    }, function (response) {
      if (response && response.success) {
        logContainer.html(response.data.markup);
      }
    });
  }

  sortTypeField.on('change', toggleMetaFields);

  form.on('submit', function (e) {
    e.preventDefault();

    const payload = {
      action: 'wcpsp_save_preset',
      nonce: wcpsp.nonce,
      preset_id: presetIdField.val(),
      preset_name: presetNameField.val(),
      sort_type: sortTypeField.val(),
      order: orderField.val(),
      meta_key: metaKeyField.val(),
      meta_type: metaTypeField.val(),
      category_ids: $('input[name="category_ids[]"]:checked').map(function () {
        return this.value;
      }).get(),
      tag_ids: $('input[name="tag_ids[]"]:checked').map(function () {
        return this.value;
      }).get(),
      set_default: $('input[name="set_default"]').is(':checked') ? 1 : 0,
    };

    $.post(wcpsp.ajax_url, payload, function (response) {
      if (response.success) {
        listWrap.html(response.data.markup);
        resetForm();
        refreshLogViewer();
      } else {
        alert(response.data.message || 'Something went wrong.');
      }
    }).fail(function () {
      alert('Request failed.');
    });
  });

  $(document).on('click', '.wcpsp-edit', function () {
    const presetId = $(this).data('preset-id');

    $.post(wcpsp.ajax_url, {
      action: 'wcpsp_get_preset',
      nonce: wcpsp.nonce,
      preset_id: presetId
    }, function (response) {
      if (!response.success) {
        alert(response.data.message || 'Preset not found.');
        return;
      }

      const preset = response.data.preset;

      presetIdField.val(preset.id);
      presetNameField.val(preset.name);
      sortTypeField.val(preset.sort_type || 'menu_order');
      orderField.val(preset.order || 'ASC');
      metaKeyField.val(preset.meta_key || '');
      metaTypeField.val(preset.meta_type || 'NUMERIC');

      $('input[name="category_ids[]"]').prop('checked', false);
      $('input[name="tag_ids[]"]').prop('checked', false);

      if (Array.isArray(preset.category_ids)) {
        preset.category_ids.forEach(function (id) {
          $('input[name="category_ids[]"][value="' + id + '"]').prop('checked', true);
        });
      }

      if (Array.isArray(preset.tag_ids)) {
        preset.tag_ids.forEach(function (id) {
          $('input[name="tag_ids[]"][value="' + id + '"]').prop('checked', true);
        });
      }

      toggleMetaFields();
      submitBtn.text('Update preset');
      cancelBtn.show();

      $('html, body').animate({ scrollTop: 0 }, 200);
    });
  });

  $(document).on('click', '.wcpsp-default', function () {
    const presetId = $(this).data('preset-id');

    $.post(wcpsp.ajax_url, {
      action: 'wcpsp_set_default_preset',
      nonce: wcpsp.nonce,
      preset_id: presetId
    }, function (response) {
      if (response.success) {
        listWrap.html(response.data.markup);
        refreshLogViewer();
      } else {
        alert(response.data.message || 'Unable to update default preset.');
      }
    });
  });

  $(document).on('click', '.wcpsp-delete', function () {
    const presetId = $(this).data('preset-id');

    if (!confirm('Delete this preset?')) {
      return;
    }

    $.post(wcpsp.ajax_url, {
      action: 'wcpsp_delete_preset',
      nonce: wcpsp.nonce,
      preset_id: presetId
    }, function (response) {
      if (response.success) {
        listWrap.html(response.data.markup);
        if (presetIdField.val() === presetId) {
          resetForm();
        }
        refreshLogViewer();
      } else {
        alert(response.data.message || 'Unable to delete preset.');
      }
    });
  });

  refreshBtn.on('click', function () {
    refreshLogViewer();
  });

  clearBtn.on('click', function () {
    if (!confirm('Clear log entries?')) {
      return;
    }

    $.post(wcpsp.ajax_url, {
      action: 'wcpsp_clear_log',
      nonce: wcpsp.nonce
    }, function (response) {
      if (response && response.success) {
        logContainer.html(response.data.markup);
      } else {
        alert(response.data.message || 'Unable to clear log.');
      }
    });
  });

  cancelBtn.on('click', function () {
    resetForm();
  });

  toggleMetaFields();
});
