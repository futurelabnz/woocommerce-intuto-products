jQuery(document).ready(function () {

	/**
	 * Collections dropdown autocomplete
	 */
	var collections = window.intuto_vars.collections;

	jQuery('#_intuto_collection_title').autocomplete({
		source: collections,
		minLength: 1,
		select: function (event, ui) {
			jQuery('#_intuto_collection_title').val(ui.item.label);
			jQuery('#_intuto_collection_id').val(ui.item.id);
		}
	});

	/**
	 * Refesh products button click
	 */
	jQuery('.refresh-intuto-products-list').on('click', function (e) {
		//if there is no default site stored, find out if there should be one.
		jQuery.ajax({
			type: "post",
			dataType: "json",
			url: intuto_vars.ajax_url,
			data: 'action=refresh_intuto_collections&security=' + intuto_vars.ajax_nonce,
			success: function (response) {
				console.log(response);
				alert(response.alert);
				window.location.reload();
			}
		});
	})

});
