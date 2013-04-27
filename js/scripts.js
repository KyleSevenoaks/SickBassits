$(function(){
	$("h1 img.logo").click( function(){
		document.location.href="index.html";
	})
	
	$("aside.read-more, article.actual img, #login").after("<span class='clear'></span>");
	
	$(".make-comment-container button").click( function(){
		$(this).hide();
		$(".comment-fieldset").show();
	});
	
	$(".slider-wrapper").uSlide({
	    speed: 1000,
	    delay: 7000,
	    animation: "fade",
	    nextFadeOutAnimation: "fade slide",
	    prevFadeInAnimation: "fade slide",
	});
	
	$("#header").sticky({topSpacing: 0});
	
});
