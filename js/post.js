var loggedin = false;

jQuery(function($) {

	var ts_active = 0;
	edCanvas = document.getElementById('posttext');
	jQuery('#comment-submit').live( 'click', function() {
		if (loggedin == true)
			window.onbeforeunload = null;
	});

	if (isUserLoggedIn) {
		// Checks if you are logged in and try to input data (To fix for ONLY private posts.)
		jQuery('.inputarea').click(function() {
			jQuery.ajax({
				type: "POST",
				url: ajaxUrl +'&action=logged_in_out&_loggedin=' + nonce,
				success: function(result) {
					if (result != 'logged_in') {
						newNotification('Please login again.');
						window.location = login_url;
					} else {
						loggedin = true;
					}
				}
			});
		});
	}

	window.onbeforeunload = function (e) {
		if (jQuery('#posttext').val()) {
	  		var e = e || window.event;
	  		if (e) { // For IE and Firefox
	    		e.returnValue = GInline.unsaved_changes;
	  		}
	  		return GInline.unsaved_changes;   // For Safari
		}
	};
	// tag suggest
	jQuery(document).ready(function() {
		$('#tags').bind('keyup', function(){
			var tags = $('#tags').val().split(',');
			$.each(tags, function(key,value) {
				tags[key] = $.trim(value);
			});
			var maybe = tags[tags.length-1];
			$('#tag-suggest').html('');
			$('#tag-suggest').attr('style','');

			if(maybe.length < 3)
				return false;

			var queryString = ajaxUrl +'&action=tag_search&q=' + maybe;
			ajaxTags = $.get(queryString, function(suggest) {
				if(!suggest.length)
					return;

				if(ts_active)
					$('#tag-suggest').html('');
				ts_active = 1;
				
				var tag_list = suggest.split("\n");
				var c = 0;
				$.each(tag_list, function(key,value) {
					if($.inArray(value, tags) == -1) {
						pos = value.indexOf(maybe);
						if(pos < 0)
							pos = 0;
							
						$('#tag-suggest').append('<div class="tag-suggest" rel="'+value+'">'+value.substr(0, pos)+'<span class="u-suggest">'+value.substr(pos, maybe.length)+'</span>'+value.substr(pos + maybe.length)+'</div>');
						c += 1;
					}
				});
				if(!c)
					return;
				$('#tag-suggest').attr('style','padding:0 2px;border:1px solid;z-index:99 !important;height:' + c * 1.6 + 'em');
				setTimeout( function() {
					jQuery('.tag-suggest').click(function() {
						var rel = this.getAttribute('rel');
						if(tags.length) {
							tags[tags.length-1] = rel;
						} else {
							tags = {0: rel};
						}
						tags[tags.length] = '';
						$('#tag-suggest').html('').attr('style','');
						$('#tags').val(tags.join(', ')).focus();
						ts_active = 0;
					});
				}, 250 );
			});
			return;
		});
	});
	/*
	* Check for new posts and loads them inline
	*/
	function getPosts(showNotification,post_id){
		if (showNotification == null) {
			showNotification = true;
		}
		toggleUpdates('unewposts');
		var queryString = ajaxUrl +'&action=get_latest_posts&load_time=' + pageLoadTime + '&frontpage=' + isFirstFrontPage + '&vp=' + postsOnPageQS + '&post_id=' + post_id;
		ajaxCheckPosts = $.getJSON(queryString, function(newPosts){
			if (newPosts != null) {
				pageLoadTime = newPosts.lastposttime;
				if ( (typeof newPosts.html == "undefined") )
					return;

				$("ul#inline-sleeve > li:first").before(newPosts.html);
				var newUpdatesLi = $("ul#inline-sleeve > li:first");
				newUpdatesLi.hide().slideDown(900, function() {});
				var counter = 0;
				$('#posttext_error, #commenttext_error').hide();
				newUpdatesLi.each(function() {
					// Add post to postsOnPageQS  list
					var thisId = $(this).attr("id");
					vpostId = thisId.substring(thisId.indexOf('-')+1);
					postsOnPageQS+= "&vp[]=" + vpostId;
					if (!(thisId in postsOnPage))
						postsOnPage.unshift(thisId);
					// Bind actions to new elements
					bindActions(this, 'post');
					if (isElementVisible(this) && !showNotification) {
						$(this).animate({backgroundColor:'transparent'}, 2500, function(){
							$(this).removeClass('newupdates');
							titleCount();
						});
					}
					localizeMicroformatDates(this);
					counter++;
				});
			}
		});
		//Turn updates back on
		toggleUpdates('unewposts');
	}
	/*
	* Submit a post via ajax
	*/
	function savePost(trigger, status) {
		var thisForm = $(trigger.target);
		var thisFormElements = $('#posttext, #tags, :input',thisForm).not('input[type=hidden]');

		var submitProgress = thisForm.find('span.progress');

		var posttext = $.trim($('#posttext').val());

		if(jQuery('.no-posts')) jQuery('.no-posts').hide();

		if ("" == posttext) {
			$("label#posttext_error").text('This field is required').show().focus();
			return false;
		}

		$('#ajaxActivity').toggle();
		toggleUpdates('unewposts');
		if (typeof ajaxCheckPosts != "undefined")
			ajaxCheckPosts.abort();
		$("label#posttext_error").hide();
		thisFormElements.attr('disabled', true);
		thisFormElements.addClass('disabled');

		submitProgress.show();
		var tags = $('#tags').val();
		if (tags == GInline.tagit) tags = '';
		var post_id = $('#post_id').val();
		var post_format = $('#post_format').val();
		var post_title = $('#posttitle').val();
		var post_citation = $('#postcitation').val();
		var post_cat = $('#post_cat').val();
		var post_status = status;
		$('div.post-' + post_id).parent().remove();

		var args = {_ajax_post:nonce, posttext: posttext, tags: tags, post_format: post_format, post_cat: post_cat, post_title: post_title, post_citation: post_citation, post_status: post_status, post_id: post_id};
		if(post_id)
			args['action'] = 'save_post';
		else
			args['action'] = 'new_post';
		var errorMessage = '';
		$.ajax({
			type: "POST",
			url: ajaxUrl,
			data: args,
			success: function(result) {
				if("0" == result)
					errorMessage = GInline.not_posted_error;

				post_id = result;
				if(post_status == 'publish') {
					$('#posttext, #postcitation, #posttitle').val('');
					$('#postcitation, #posttitle').blur();
					$('#tags').val(GInline.tagit);
					post_id = null;
				}
				if(errorMessage != '')
					newNotification(errorMessage);

				if (isFirstFrontPage && result != "0") {
					getPosts(false,post_id);
				} else if (!isFirstFrontPage && result != "0") {
					newNotification(GInline.update_posted);
				}
				submitProgress.fadeOut();
				thisFormElements.attr('disabled', false);
				thisFormElements.removeClass('disabled');
				if(post_status != 'publish' && (result - 0) == result) {
					$('#post_id').val(result);
				} else {
					$('#post_id').val('');
				}
				$('#new_post .thickbox').each(function() {
					var href = $(this).attr('href').replace(/(post_id=)[^\&]+\&/, '$1' + $('#post_id').val() + '&');
					$(this).attr('href', href);
				});
			  },
			error: function(XMLHttpRequest, textStatus, errorThrown) {
				submitProgress.fadeOut();
				thisFormElements.attr('disabled', false);
				thisFormElements.removeClass('disabled');
			},
			timeout: 60000
		});
		thisFormElements.blur();
		toggleUpdates('unewposts');
		$('#ajaxActivity').toggle();
		return false;
	}

	function newNotification(message) {
		$("#notify").stop(true).prepend(message + '<br/>')
			.fadeIn()
			.animate({opacity: 0.7}, 2000)
			.fadeOut('1000', function() {
				$("#notify").html('');
			}).click(function() {
				$(this).stop(true).fadeOut('fast').html('');
			});
	}

	/*
	* Handles tooltips for the recent-comment widget
	* param: anchor link
	*/
	function tooltip(alink){
		xOffset = 10;
		yOffset = 20;
		alink.hover(function(e){
			this.t = this.title;
			this.title = "";
			$("body").append("<div id='tooltip'>"+ this.t +"</div>");
			$("#tooltip")
				.css("top",(e.pageY - yOffset) + "px")
				.css("left",(e.pageX + xOffset) + "px")
				.fadeIn("fast");
	    },
		function(){
			this.title = this.t;
			$("#tooltip").remove();
	    });
		alink.mousemove(function(e){
			$("#tooltip")
				.css("top",(e.pageY - yOffset) + "px")
				.css("left",(e.pageX + xOffset) + "px");
		});
	};

	function isElementVisible(elem) {
	    elem = $(elem);
		if (!elem.length) {
	        return false;
	    }
	    var docViewTop = $(window).scrollTop();
	    var docViewBottom = docViewTop + $(window).height();

	    var elemTop = elem.offset().top;
	    var elemBottom = elemTop + elem.height();
		var isVisible = ((elemBottom >= docViewTop) && (elemTop <= docViewBottom)  && (elemBottom <= docViewBottom) &&  (elemTop >= docViewTop) );
	    return isVisible;
	}

	function toggleUpdates(updater){
		switch (updater) {
			case "unewposts":
				if (0 == getPostsUpdate) {
					getPostsUpdate = setInterval(getPosts, updateRate);
				}
				else {
					clearInterval(getPostsUpdate);
					getPostsUpdate = '0';
				}
				break;
		}
	}

	function titleCount() {
		if (isFirstFrontPage) {
			var n = $('li.newupdates').length;
		} else {
			var n = newUnseenUpdates;
		}
		if ( n <= 0 ) {
			if (document.title.match(/\([\d+]\)/)) {
				document.title = document.title.replace(/(.*)\([\d]+\)(.*)/, "$1$2");
			}
			fluidBadge("");
		} else {
			if (document.title.match(/\((\d+)\)/)) {
				document.title = document.title.replace(/\((\d+)\)/ , "(" + n + ")" );
			} else {
				document.title = '(1) ' + document.title;
			}
			fluidBadge(n);
		}
	}

	/**
	 * Sets the badge for Fluid app enclosed P2. See http://fluidapp.com/
	 */
	function fluidBadge(value) {
		if (window.fluid) window.fluid.dockBadge = value;
	}

	/**
	 * Sets up a jQuery-collection of textareas to expand vertically based
	 * on their content.
	 */
	function autgrow(textareas, min) {
		function sizeToContent(textarea) {
			textarea.style.height = (min*1.4) + 'em';
			if (textarea.scrollHeight > textarea.clientHeight) {
				textarea.style.height = textarea.scrollHeight + 'px';
			}
		}
		textareas.css('overflow', 'hidden');

		function resizeSoon(e) {
			var textarea = this;
			setTimeout(function() {
				sizeToContent(textarea);
			}, 1);
		}
		textareas.keydown(resizeSoon); // Catch regular character keys
		textareas.keypress(resizeSoon); // Catch enter/backspace in IE, and held-down repeated keys
		textareas.focus(resizeSoon);
	}

	function jumpToTop() {
		$.scrollTo('#main', 150);
	}

//@todo - not implemented
	function inlineEditPost(postId, element) {
		// Set up editor

		function defaultText(input, text) {
			function onFocus() {
				if (this.value == text) {
					this.value = '';
				}
			}
			function onBlur() {
				if (!this.value) {
					this.value = text;
				}
			}
			jQuery(input).focus(onFocus).blur(onBlur);
			onBlur.call(input);
		}

		jQuery.getJSON(ajaxUrl, {action: 'get_post', _inline_edit: nonce, post_ID: 'content-' + postId}, function(post) {
			var jqe = jQuery(element);
			jqe.addClass('inlineediting');
			jqe.find('.tags').css({display: 'none'});
			jqe.find('.postcontent > *').hide();
			if (post.type == 'page') {
				jQuery('#main h2').hide();
			}

			var postContent = jqe.find('.postcontent');

			var titleDiv = document.createElement('div');

			if (post.type == 'post' || post.type == 'page') {
				var titleInput = titleDiv.appendChild(
					document.createElement('input'));
				titleInput.type = 'text';
				titleInput.className = 'title';
				defaultText(titleInput, GInline.title);
				titleInput.value = post.title;
				postContent.append(titleDiv);
			}

			var cite = '';
			if (post.type == 'quote') {
				var tmpDiv = document.createElement('div');
				tmpDiv.innerHTML = post.content;
				var cite = $(tmpDiv).find('cite').remove().html();
				if (tmpDiv.childNodes.length == 1 && tmpDiv.firstChild.nodeType == 1) {
					// This _should_ be the case, else below is
					// to handle an unexpected condition.
					post.content = tmpDiv.firstChild.innerHTML;
				} else {
					post.content = tmpDiv.innerHTML;
				}
			}

			var editor = document.createElement('textarea');
			editor.className = 'posttext';
			editor.value = post.content;
			autgrow($(editor), 5);
			jqe.find('.postcontent').append(editor);

			var citationDiv = document.createElement('div');
			if (post.type == 'quote') {
				var citationInput = citationDiv.appendChild(
					document.createElement('input'));
				citationInput.type = 'text';
				citationInput.value = cite;
				defaultText(citationInput, GInline.citation);
				postContent.append(citationDiv);
			}

			var bottomDiv = document.createElement('div');
			bottomDiv.className = 'row2';

			if (post.type != 'page') {
				var tagsInput = document.createElement('input');
				tagsInput.name = 'tags';
				tagsInput.className = 'tags';
				tagsInput.type = 'text';
			   	tagsInput.value = post.tags.join(', ');
				defaultText(tagsInput, GInline.tagit);
				bottomDiv.appendChild(tagsInput);
			} else {
				var tagsInput = '';
			}

			function tearDownEditor() {
				$(titleDiv).remove();
				$(bottomDiv).remove();
				$(citationDiv).remove();
				$(editor).remove();
				jqe.find('.tags').css({display: ''});
				jqe.find('.postcontent > *').show();
				jqe.removeClass('inlineediting');
				if (post.type == 'page') {
					jQuery('#main h2').show();
				}
			}
			var buttonsDiv = document.createElement('div');
			buttonsDiv.className = 'buttons';
			var saveButton = document.createElement('button');
			saveButton.innerHTML = GInline.save;
			jQuery(saveButton).click(function() {
				var tags = tagsInput.value == GInline.tagit ? '' : tagsInput.value;
				var args = {
					action:'save_post',
					_inline_edit: nonce,
					post_ID: 'content-' + postId,
					content: editor.value,
					tags: tags
				};

				if (post.type == 'post' || post.type == 'page') {
					args.title = titleInput.value == GInline.title ? '' : titleInput.value;
				} else if (post.type == 'quote') {
					args.citation = citationInput.value == GInline.citation ? '' : citationInput.value;
				}

				jQuery.post(
					ajaxUrl,
					args,
					function(result) {
						// Preserve existing H2 for posts
						jqe.find('.postcontent').html(
							(post.type == 'post') ?
							jqe.find('h2').first() : '');
						if (post.type == 'quote') {
							jqe.find('.postcontent').append(
								'<blockquote>' + result.content + '</blockquote>');
						} else {
							jqe.find('.postcontent').append(result.content);
						}
						if (post.type == 'page') {
							jQuery('#main h2').html(result.title);
						} else {
							jqe.find('span.tags').html(result.tags);
							if (!isSingle) {
								jqe.find('h2 a').html(result.title);
							} else {
								jqe.find('h2').html(result.title);
							}
						}
						tearDownEditor();
					},
					'json');
				saveButton.parentNode.insertBefore(document.createElement('img'), saveButton).src = templateDir +'/i/indicator.gif';
			});
			var cancelButton = document.createElement('button');
			cancelButton.innerHTML = GInline.cancel;
			jQuery(cancelButton).click(tearDownEditor);
			buttonsDiv.appendChild(saveButton);
			buttonsDiv.appendChild(cancelButton);
			bottomDiv.appendChild(buttonsDiv);
			jqe.find('.postcontent').append(bottomDiv);
			// Trigger event handlers
			editor.focus();
			jQuery('input[name="tags"]').suggest(ajaxUrl + '&action=tag_search', {delay: 350, minchars: 2, multiple: true, multipleSep: ", "});
		});
	}


	function bindActions(element, type) {
		switch (type) {
			case "post" :
				var thisPostEditArea;
				if (inlineEditPosts != 0 && isUserLoggedIn) {
					thisPostEditArea = $(element).children('div.editarea').eq(0);
					jQuery(element).find('a.edit-post-link:first').click(
						function(e) {
							var postId = this.href.match(/post=[0-9]+/).match(/[0-9]+/);
							inlineEditPost(postId, element);
							return false;
						});
				}
				$(".single #postlist li > div.postcontent, .single #postlist li > h4, li[id^='prologue'] > div.postcontent, li[id^='comment'] > div.commentcontent, li[id^='prologue'] > h4, li[id^='comment'] > h4").hover(function() {
					$(this).parents("li").eq(0).addClass('selected');
				}, function() {
					$(this).parents("li").eq(0).removeClass('selected');
				});
				break;
		}
	}

	function localizeMicroformatDates(scopeElem) {
		(scopeElem? $('abbr', scopeElem) : $('abbr')).each(function() {
			var t = $(this);
			var d = locale.parseISO8601(t.attr('title'));
			if (d) t.html(GInline.date_time_format.replace('%1$s', locale.date(GInline.time_format, d)).replace('%2$s', locale.date(GInline.date_format, d)));

		});
	}


	/* On-load */

	commentsLists = $(".commentlist");

	locale = new wp.locale(wp_locale_txt);

	if(!window.location.href.match('#'))
		$('#posttext').focus();

	$(".single #postlist li > div.postcontent, .single #postlist li > h4, li[id^='prologue'] > div.postcontent, li[id^='comment'] > div.commentcontent, li[id^='prologue'] > h4, li[id^='comment'] > h4").hover(function() {
		$(this).parents("li").eq(0).addClass('selected');
	}, function() {
		$(this).parents("li").eq(0).removeClass('selected');
	});

	$.ajaxSetup({
	  timeout: updateRate - 2000,
	  cache: false
	});

	$("#directions-keyboard").click(function(){
		$('#help').toggle();
		return false;
	});

	$("#help").click(function() {
		$(this).toggle();
	});

	// Activate inline editing plugin
	if ((inlineEditPosts || inlineEditComments ) && isUserLoggedIn) {
		$.editable.addInputType('autogrow', {
		    element : function(settings, original) {
		        var textarea = $('<textarea class="expand" />');
		        if (settings.rows) {
		            textarea.attr('rows', settings.rows);
		        } else {
		            textarea.attr('rows', 4);
		        }
		        if (settings.cols) {
		            textarea.attr('cols', settings.cols);
		        } else {
		            textarea.attr('cols', 45);
		        }
				textarea.width('95%');
		        $(this).append(textarea);
		        return(textarea);
		    },
		    plugin : function(settings, original) {
				autgrow($('textarea', this), 3);
		    }
		});
	}

	// Set tabindex on all forms
	var tabindex = 4;
	$('form').each(function() {
		$(':input',this).not('#subscribe, input[type=hidden]').each(function() {
        	var $input = $(this);
			var tabname = $input.attr("name");
			var tabnum = $input.attr("tabindex");
			if(tabnum > 0) {
				index = tabnum;
			} else {
				$input.attr("tabindex", tabindex);
			}
			tabindex++;
		});
     });

	// Turn on automattic updating
	if (prologuePostsUpdates) {
		toggleUpdates('unewposts');
	}
	if (prologueCommentsUpdates) {
			toggleUpdates('unewcomments');
	}

	// Check which posts are visibles and add to array and comment querystring
	$("ul#inline-sleeve > li").each(function() {
		var thisId = $(this).attr("id");
		vpostId = thisId.substring(thisId.indexOf('-') + 1);
		postsOnPage.push(thisId);
		postsOnPageQS += "&vp[]=" + vpostId;
	});


	// Bind actions to comments and posts
	jQuery('ul#inline-sleeve .post, body .page').each(function() {bindActions(this, 'post');});

	function removeYellow() {
//@todo: highlight new posts
		if (isFirstFrontPage) {
			$('#main > ul > li.newupdates').each(function() {
				if (isElementVisible(this)) {
					$(this).animate({backgroundColor:'transparent'}, {duration: 2500});
					$(this).removeClass('newupdates');
				}
			});
		}
		titleCount();
	}

	// Actvate autgrow on textareas
	if (isFrontPage) {
		autgrow($('#posttext, #comment'), 6);
	}

	// Catch new posts submit
	$("#new_post .searchsubmit").click(function(trigger) {
		var status = $(this).attr('id');
		savePost(trigger, status);
		trigger.preventDefault();
	});

	// Hide error messages on load
	$('#posttext_error, #commenttext_error').hide();

 	// Check if new comments or updates appear on scroll and fade out
	$(window).scroll(function() {removeYellow();});

	localizeMicroformatDates();
});

function send_to_editor( media ) {
	if ( jQuery('textarea#posttext').length ) {
		jQuery('textarea#posttext').val( jQuery('textarea#posttext').val() + media );
		tb_remove();
	}
}