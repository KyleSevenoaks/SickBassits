$(function(){
	$("h1 img.logo").click( function(){
		document.location.href="index.html";
	})
	
	$("aside.read-more, article.actual img, #login, button.mobile-menu").after("<span class='clear'></span>");
	
	$(".make-comment-container button").click( function(){
		$(this).hide();
		$(".comment-fieldset").show();
	});
	
	$("#contact button").click( function(){
		$(this).hide();
		$(".contact-form").show();
	});
	
	$("#contact button.submit").click( function(e){
		e.preventDefault; //only for the dummy page, not for uCMS interation
		$(this).parents(".contact-form").hide();
		$(".success").show();
	});
	
	$(".slider-wrapper").uSlide({
	    speed: 1000,
	    delay: 7000,
	    animation: "fade",
	    nextFadeOutAnimation: "fade slide",
	    prevFadeInAnimation: "fade slide",
	});
	
	$("#header").sticky({topSpacing: 0});
	
	$("button.mobile-menu").click( function(){
		$("nav").fadeToggle('fast');
	});
	
});
