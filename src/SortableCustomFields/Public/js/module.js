$(document).ready(function() {
	function removeDateSort(){
		var tempSort = $('.table-conversations').attr("data-sorting_sort_by")
		$('tr span[data-sort-by="'+tempSort+'"]').text(function(index, oldText) {
		 // Replace 'oldText' with 'newText' if a condition is met
		 if (oldText.includes('↑') || oldText.includes('↓')) {
			 return oldText.replace('↑', '').replace('↓', '');
		 } else {
			 return oldText;
		 }
	 }); 
	 }
	
	var originalMainFunction = convListSortingInit;
	convListSortingInit = function() {
		// Call the original function
		originalMainFunction();
		if ($('.custom-field-tr:contains("↑")').length > 0 || $('.custom-field-tr:contains("↓")').length > 0 ){
			removeDateSort()
			}
	};

	// FreeScout disables empty search filters after render, so the next submit
	// drops them from the URL. Keep active filters enabled (empty value included).
	function enableActiveSearchFilters() {
		$('#search-filters .form-group.active :input').prop('disabled', false);
	}

	if ($('.section-search form').length) {
		enableActiveSearchFilters();
		$('.section-search form').on('submit', enableActiveSearchFilters);
	}
});
