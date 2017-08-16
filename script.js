jQuery(function(){
	var $puzzles = jQuery('div.puzzles');	
	$puzzles.find('a[href$="puzzle-solution"]').each(function() {
		var $a = jQuery(this);
		var $hiding = $a.parents('li').find('ul');
		$a.click(function(e) {
			e.preventDefault();
			$hiding.toggle();
		});
	});
});
