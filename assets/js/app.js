$( document ).ready(function() {

	setLoadingImage();
	
	$("#search_activate").click(function(){

		$("#search").show();
		$(this).hide();
		$("#q").focus();

	});

	function stripQuery(imageSource)
	{
		var index = imageSource.indexOf('?');

		if (index !== -1)
		{
			imageSource = imageSource.substring(0, index);
		}

		return imageSource;
	}

	function updateTimestamp(url)
	{
		return stripQuery(url) + '?t=' + new Date().getTime();
	}

	if(window.location.href.indexOf("search?q=") !== -1)
	{
		$("#search_activate").click();
	}

	$("#image-nav-rotate").click(function(event){

		if (window.fullSize)
		{
			return;
		}

		var imageSource = stripQuery($("#slider img").attr('src'));

		var from = /(\/cache\/detail\/)|(\/detail\/)|(\/original\/)/;
		var to = '/rotate/';
		var toDetail = '/cache/detail/';

		var imageSourceDetail = imageSource.replace(from, toDetail);
		var imageSourceRotate = imageSource.replace(from, to);

		if (event.ctrlKey || event.metaKey)
		{
			imageSourceRotate += '?invert';
		}

		var jqxhr = $.ajax(imageSourceRotate)
			.done(function(content) {

				$("#slider img").attr('src', updateTimestamp(imageSourceDetail));

				var result = JSON.parse(content);

				$('.images .image').each(function () {
					var linkHref = stripQuery($(this).attr('href'));

					if (linkHref == imageSource)
					{
						// update link to the image
						linkHref = updateTimestamp(linkHref);

						// update thumbnail url
						$(this).find('img').each(function(){
							$(this).attr('src', updateTimestamp($(this).attr('src')));
						});
					}
					$(this).attr('href', linkHref);
				});
			})
			.fail(function() {
				console.log('Request for fetching info failed');
			});

	});

	$("#image-nav-info").click(function(){

		// if the info block is already visible than act as a toggle
		if ($("#info").is(":visible"))
		{
			$("#info").hide();
			return;
		}

		$('#info_inner').html('');

		var imageSource = $("#slider img").attr('src');

		var from = /(\/cache\/detail\/)|(\/detail\/)|(\/original\/)/;
		var to = '/info/';

		imageSource = imageSource.replace(from, to);

		var jqxhr = $.ajax(imageSource)
		.done(function(content) {

			var result = JSON.parse(content);

			if (result.result == true)
			{
				var responseHtml = $('<table/>');
				responseHtml.append( '<tr><td>Filename</td><td>' + result.filename + '</td></tr>');
				responseHtml.append( '<tr><td>Folder</td><td>' + result.folder + '</td></tr>');

				var data = result.data;

				if (data)
				{
					$.each(result.data, function(key, val){

						if ($.isArray(val))
						{
							var items=[];
							items.push('<td>'+ key +'</td>');
							items.push('<td>'+ val.join(', ') +'</td>');
							responseHtml.append($('<tr/>', {html: items.join('')}));
						}
						// show object as separate properties
						else if (typeof(val) == 'object')
						{
							$.each(val, function(subkey, subval){
								var items=[];

								// even more objects at this level, then just show a JSON representation
								if (typeof(subval) == 'object')
								{
									val = JSON.stringify(val);
								}

								items.push('<td>'+ key + ' (' + subkey + ')</td>');
								items.push('<td>'+ subval +'</td>');

								responseHtml.append($('<tr/>', {html: items.join('')}));
							});

						}
						else
						{
							var items=[];
							items.push('<td>'+ key +'</td>');
							items.push('<td>'+ val +'</td>');
							responseHtml.append($('<tr/>', {html: items.join('')}));
						}
						
					});
				}
				else
				{
					responseHtml.append( '<tr><td colspan="2">No additional information could be retrieved</td></tr>');
				}

			}
			else
			{
				var responseHtml = $('<p>Error fetching file information</p>');
			}

			$('#info_inner').append(responseHtml);

			$("#info").show();

		})
		.fail(function() {
			console.log('Request for fetching info failed');
		});

	});

	$("#info_close").click(function(){

		$("#info").hide();

	});

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

		window.fullSize = fullSize;

		updateFullSizeButton();

		if (fullSize === true)
		{
			$(this).attr('title', 'Show resized version');
		}
		else
		{
			$(this).attr('title', 'Show original image');
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

		var jqxhr = $.ajax(window.appRoot + "/?full-size=" +  fullSize.toString())
		.done(function(content) {

		})
		.fail(function() {
			console.log('Request for size changing failed');
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

	function closeGallery()
	{
		$('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
		$("#overlay").hide();
	}

	function setLoadingImage()
	{
		$('#overlay #slider img').attr('src', window.appRoot + '/assets/images/loading.gif');
	}

	$("#slider").touchwipe({
		wipeLeft: function() { showNextImage(); },
		wipeRight: function() { showPreviousImage(); },
		wipeUp: function() {},
		wipeDown: function() {},
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
			case 27: // esc
				closeGallery();
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
		closeGallery();
	});

	$('.images .image').click(function(e){
		e.preventDefault();
		startGallery($(this));
	});

	/* CREATE THUMBNAILS AFTER LOAD */
	var imagesToGenerate = $('.images .image img[data-src]');

	var thumbnailsFailed = [];

	function generateThumbnail(image, imagesToGenerate)
	{
		var jqxhr = $.ajax(window.appRoot + "/update_thumbnail" + image.attr('data-src'))
		.done(function(content) {
			if(content == 'success') {
				image.attr('src', window.appRoot + '/cache/thumbnail' + image.attr('data-src'));
			} else {
				image.parent().remove();
				console.log('Could not generate ' + image.attr('data-src'));
				thumbnailsFailed.push(image.attr('data-src'));
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

				if(thumbnailsFailed.length > 0){
					var span = $('<span></span>').attr('title', thumbnailsFailed.join("\n"));
					var imagesText = thumbnailsFailed.length == 1 ? 'image' : 'images';
					span.html('For <strong>' + thumbnailsFailed.length + ' ' + imagesText + '</strong> it was not possible to generate a thumbnail');

					$('#information').html(span);
				}
			} else {
				generateThumbnail(nextImageObject, imagesToGenerate);
			}

		})
		.fail(function() {
			console.log('Request for generation thumbnail failed');
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

	$('#system').on('click', function(){

		$('#log').toggle();

	});

	function updateFullSizeButton()
	{
		if (window.fullSize)
		{
			$("#image-nav-rotate").css({'opacity':0.5, 'cursor': 'default'}).attr('title', 'You can\'t rotate the original image');
		}
		else
		{
			$("#image-nav-rotate").css({'opacity':1, 'cursor': 'pointer'}).attr('title', 'Click to rotate');
		}
	}

	updateFullSizeButton();
});
