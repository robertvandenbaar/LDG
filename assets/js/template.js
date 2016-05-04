/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
 */

$( document ).ready(function() {

	setLoadingImage();

    $("#image-nav-size").click(function () {

        if ($(this).hasClass('active'))
        {
            var from = '/original/';
            var to = '/detail/';
            var fullSize = false;
        }
        else
        {
            var from = /(\/cache\/detail\/)|(\/detail\/)/;
            var to = '/original/';
            var fullSize = true;
        }

        $('#slider img').each(function () {

            var imageSource = $(this).attr('src');
            imageSource = imageSource.replace(from, to);

            setLoadingImage();

            $(this).attr('src', imageSource);
        });

        $('.images .image').each(function () {
            var linkHref = $(this).attr('href');
            linkHref = linkHref.replace(from, to);
            $(this).attr('href', linkHref);
        });

        var jqxhr = $.ajax(window.appRoot + "/ajax.change.size.php?full-size=" +  fullSize.toString())
        .done(function(html) {

        })
        .fail(function() {
            console.log('Ajax request ajax.change.size.php failed');
        });

        $(this).toggleClass('active');

    });

    window.currentImage = 0;
    window.totalImages = 0;

    function startGallery(a) {

		$("#overlay").show();

		setTimeout(function(){

			setLoadingImage();

			$('#overlay #slider img').attr('src', a.attr('href'));

			var counter = 0;

			window.totalImages = $('.images .image').length;

			$('.images .image').each(function(){

				counter++;

				if($('#overlay #slider img').attr('src') == $(this).attr('href')) {
					window.currentImage = counter;
				}
			});

			$("#image-nav-next").css({'opacity':1, 'cursor': 'pointer'});
			$("#image-nav-prev").css({'opacity':1, 'cursor': 'pointer'});

			/* last image is loaded */
			if (!hasNextImage()) {
				$("#image-nav-next").css({'opacity':0.5, 'cursor': 'default'});
			}

			/* first image is loaded */
			if (!hasPreviousImage()) {
				$("#image-nav-prev").css({'opacity':0.5, 'cursor': 'default'});
			}

		}, 50);

    }

    function hasNextImage() {
        return window.currentImage < window.totalImages;
    }

    function hasPreviousImage() {
        return window.currentImage > 1;
    }

    function showNextImage() {
        if (hasNextImage()) {
			startGallery($('.images .image:nth-child(' + (window.currentImage + 1) +')'));
        }
    }

    function showPreviousImage() {
        if (hasPreviousImage()) {
            startGallery($('.images .image:nth-child(' + (window.currentImage - 1) +')'));
        }
    }

    function setLoadingImage()
    {
        $('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
    }

    $("#slider").touchwipe({
        wipeLeft: function() { showNextImage(); },
        wipeRight: function() { showPreviousImage(); },
        wipeUp: function() { },
        wipeDown: function() {  },
        min_move_x: 10,
        min_move_y: 10,
        preventDefaultEvents: true
    });

	$(document).keydown(function(e) {
		switch(e.which) {
			case 37: // left
				showPreviousImage();
				break;
			case 38: // up
				break;
			case 39: // right
				showNextImage();
				break;
			case 40: // down
				break;

			default: return; // exit this handler for other keys
		}
		e.preventDefault(); // prevent the default action (scroll / move caret)
	});

    $('#image-nav-prev').click(function(e){
        e.preventDefault();
        showPreviousImage();
    });

    $('#image-nav-next').click(function(e){
        e.preventDefault();
        showNextImage();
    });

    $('#image-nav-close').click(function(){
       $('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
       $("#overlay").hide();
    });

    $('.images .image').click(function(e){
        e.preventDefault();
        startGallery($(this));
    });

	/* CREATE THUMBNAILS AFTER LOAD */
	var imagesToGenerate = $('.images .image img[data-src]');

	function generateThumbnail(image, imagesToGenerate)
	{
		var jqxhr = $.ajax(window.appRoot + "/ajax.generate.php?file=" +  image.attr('data-src'))
		.done(function(html) {
			if(html == 'success') {
				image.attr('src', window.appRoot + '/cache/thumbnail' + image.attr('data-src'));
			} else {
				console.log('Could not generate ' + image.attr('data-src'));
			}

			image.removeClass('loading');

            var nextImage = false;
            var nextImageObject = false;

            imagesToGenerate.each(function(){

                if (nextImage == true){
                    nextImageObject = $(this);
                    nextImage = false;
                }

                if($(this).attr('id') == image.attr('id')){
                    nextImage = true;
                }
            });

            if(nextImageObject == false){
                $('#information').html('');
            } else {
                generateThumbnail(nextImageObject, imagesToGenerate);
            }
			
		})
		.fail(function() {
			console.log('Ajax request ajax.generate.php failed');
		})
		.always(function() {
			//alert( "complete" );
		});
	}

	if (imagesToGenerate.length > 0)
	{
		$('#information').append($("<span>Generating " + imagesToGenerate.length + " thumbnails in the background ...</span>").attr("id", "generating-images"));

		generateThumbnail(imagesToGenerate.first(), imagesToGenerate);
	}


});
