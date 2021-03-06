define(function(require, exports, module) {

	var 
		$ = require('jquery'),
		UWAP = require('uwap-core/js/core'),

		AddCommentController = require('AddCommentController'),
		MediaPlayerController = require('MediaPlayerController'),
		ViewController = require('ViewController'),
		moment = require('uwap-core/js/moment'),
		hogan = require('uwap-core/js/hogan')
		;

	var tmpl = {
		"feedItem": require('uwap-core/js/text!templates/feedItem.html'),
		"feedItemFile": require('uwap-core/js/text!templates/feedItemFile.html'),
		"feedItemComment": require('uwap-core/js/text!templates/feedItemComment.html'),
		"participant":  require('uwap-core/js/text!templates/participant.html')
	};


	var FeedController = function(pane, app, viewconfig) {
		this.pane = pane;
		this.app = app;

		this.viewconfig = viewconfig || {};


		this.groups = {};

		this.currentRange = null;
		this.loadeditems = {};
		this.selector = {};
		this.view = {
			view: 'feed'
		};

		var vbcel = $('<div class="feedcontainer"></div>')
			.appendTo(this.pane.el);

		this.pane.el.find('.feedcontainer').addClass('view-' + this.view.view);

		this.viewcontroller = new ViewController($("#viewbarcontroller"));
		this.viewcontroller.onChange($.proxy(this.viewchange, this));


		this.mediaplayer = new MediaPlayerController(this.pane.el);

		this.pane.el.on('click', '.actEnableComment', $.proxy(this.enableComment, this));
		this.pane.el.on('click', '.actDelete', $.proxy(this.deleteItem, this));

		this.pane.el.on('click', '#postEnableBtn', $.proxy(this.postEnable, this));
		this.pane.el.on('click', '#postDisableBtn', $.proxy(this.postDisable, this));

		this.pane.el.on('click', '.responseOption', $.proxy(this.respond, this));


		this.templates = {
			"itemTmpl": hogan.compile(tmpl.feedItem),
			"itemTmplFile": hogan.compile(tmpl.feedItemFile),
			"commentTmpl": hogan.compile(tmpl.feedItemComment),
			"participant": hogan.compile(tmpl.participant)
		};




		// this.load();
		setInterval($.proxy(this.update, this), 5000);

	}

	FeedController.prototype.setMyResponse = function(target, status) {
		var text = [
			{
				'yes': 'Attend',
				'maybe': 'Maybe',
				'no': 'Appologize'
			},
			{
				'yes': 'I&apos;m attending',
				'maybe': 'I&apos;m maybe attending',
				'no': 'I&apos;m appologized'
			}
		];
		var icon = '<i class="icon-ok icon-white"></i> ';

		// console.log("    › Set my response", target);

		target.find('.responseOption').each(function(i, opt) {
			// console.log("Response options is ", opt);

			cur = $(opt).data('status');
			if (cur === status) {
				// console.log("SETTIN STATUS TO BE ", status);
				$(this).removeClass('btn-small');
				$(this).removeClass('btn-mini');
				$(this).html(icon + text[1][cur]);
			} else {
				$(this).removeClass('btn-small');
				$(this).addClass('btn-mini');
				$(this).html(text[0][cur]);
			}	
		});


	}


	FeedController.prototype.respond = function(e) {
		var that = this;
		if (e) e.preventDefault();
		var targetItem = $(e.currentTarget).closest('div.item');
		var item = targetItem.data('object');
		var status = $(e.currentTarget).data('status');
		console.log("Response with ", status, item);

		var response = {};
		response['uwap-acl-read'] = item['uwap-acl-read'];
		response.inresponseto = item.id;
		response.status = status;



		UWAP.feed.respond(response, function() {
			console.log("RESPOND COMPLETE");
			that.setMyResponse(targetItem, status);
		});

	}


	FeedController.prototype.setuser = function(u) {
		this.user = u;
	}
	FeedController.prototype.setgroups = function(groups) {
		this.groups = groups;
	}

	FeedController.prototype.enableComment = function(e) {
		e.preventDefault();

		var targetItem = $(e.currentTarget).closest('div.item');
		$(e.currentTarget).hide();
		var item = targetItem.data('object');
		// console.log("About to enable comment", this.app.user, targetItem, item);
		var cc = new AddCommentController(this.app.user, item, targetItem.find('div.postcomment'));
		cc.onPost($.proxy(this.post, this));

	}

	FeedController.prototype.deleteItem = function(e) {
		var that = this;
		e.preventDefault();
		var currentItem = $(e.currentTarget).closest('.item');
		var item = currentItem.data('object');
		console.log('About to delete ', currentItem.data());

		UWAP.feed.delete(item.id, function(data) {
			console.log("Delete response Received", data);
			that.load();
		});

	}



	FeedController.prototype.addItem = function(item) {
		var that = this;


		// console.log ("  ›››› ADD ITEM »›››››");
		item.timestamp = moment(item.ts).format();
		// console.log("Working with ", item.ts, item.timestamp);

		item.groupnames = [];
		if (item.groups) {
			$.each(item.groups, function(i, g) {
				if (that.groups[g]) {
					item.groupnames.push(that.groups[g]);
				} else {
					item.groupnames.push(g);
				}
			});
		}

		if (item.activity && item.activity.actor) {
			if (item.activity.actor.objectType === 'person') {
				item.activity.actor.image = {url: UWAP.utils.getEngineURL('/api/media/user/' + item.activity.actor.a)}
			} else if (item.activity.actor.objectType === 'client') {
				item.activity.actor.image = {url: UWAP.utils.getEngineURL('/api/media/logo/client/' + item.activity.actor.id)}
			}

			item.activity.actor.type = {};
			item.activity.actor.type[item.activity.actor.objectType] = true;

		}

		if (item.activity.verb) {
			item.activity.verb_ = {};
			item.activity.verb_[item.activity.verb] = true;
		}

		if (item.activity.object) {
			item.activity.object_ = {};
			item.activity.object_[item.activity.object.objectType] = item.activity.object;
		}


		item.viewconfig = this.viewconfig;


		/*
		if (item.user) {
			item.user.profileimg = UWAP.utils.getEngineURL('/api/media/user/' + item.user.a);
		}
		if (item.client) {
			item.client.profileimg = UWAP.utils.getEngineURL('/api/media/logo/client/' + item.client['client_id']);
		}
		*/


		// console.log("Testing article class", item.class)
		if ($.isArray(item.class) && $.inArray('article', item.class) !== -1) {
			// console.log("MATCH:", item.class, ' ' + $.inArray('article', item.class));
			// console.log("ARTICLE", item);
			item.message = item.message.replace(/([\n\r]{2,})/gi, '</p><p class="articleParagraph">');
		}

		if (item.hasClass('comment')) {
			this.addComment(item);
		} else if (item.hasClass('response')) {
			this.addResponse(item);
		} else {
			this.addPost(item);
		}

	}


	FeedController.prototype.addPost = function(item) {
		var 
			h,
			feedcontainer = this.pane.el.find('.feedcontainer');

		if (item.activity) {
			// console.log("Adding post [activity] ", item);
		}
		
		if (this.view.view === 'media') {
			// TODO TODO ADD TEMPLATE FOR MEDIA ITEMS. Look in DeprecatedJQUery templates folder to find old template...
			// h = $("#itemMediaTmpl").tmpl(item);
			// feedcontainer.find('ul').prepend(h);

		} else if (this.view.view === 'file') {

			// h = $("#itemFileTmpl").tmpl(item);
			// feedcontainer.prepend(h);

			// DISABLED BECAUSE NOT YET TESTED.
			// h = $(this.templates['itemTmplFile'].render(item));
			// h.data('object', item).prependTo(feedcontainer);

		} else {

			h = $(this.templates['itemTmpl'].render(item));
			h.data('object', item).prependTo(feedcontainer);
		}

		
		this.loadeditems[item.id] = h;
	}

	FeedController.prototype.addResponse = function(item) {
		// console.log("Adding a response", item['uwap-userid'], item['inresponseto']);
		if (this.loadeditems[item.inresponseto]) {
			// console.log("Adding YES", this.loadeditems[item.inresponseto]);
			if (item['uwap-userid'] === this.app.user.userid) {
				// console.log("MY RESPONSE", item);
				this.setMyResponse(this.loadeditems[item.inresponseto], item.status);
			}
			
			item.statusItem = {};
			item.statusItem[item.status] = true;

			var h = $(this.templates.participant.render(item)).data('object', item);
			// var h = $("#participantTmpl").tmpl(item);
			this.loadeditems[item.inresponseto].find('table.participants').append(h);
		}
	}

	FeedController.prototype.addComment = function(item) {
		// console.log("Add comment");
		if (this.loadeditems[item.inresponseto]) {
			// console.log("found item", item);
			// var h = $("#commentTmpl").tmpl(item);
			var h = $(this.templates['commentTmpl'].render(item));
			this.loadeditems[item.inresponseto].find('div.comments').append(h);
		}
	}


	FeedController.prototype.getSettings = function() {
		var s = {};
		for (var k in this.selector) {
			if (this.selector.hasOwnProperty(k)) {
				s[k] = this.selector[k]
			}
		}
		
		if (this.view.view === 'media') {
			s['class'] = ['media'];
		}
		if (this.view.view === 'file') {
			s['class'] = ['file'];
		}
		if (this.view.view === 'calendar') {
			s['class'] = ['calendar'];
		}

		return s;
	}


	FeedController.prototype.setSelector = function(selector) {

		var prevGroup = (this.selector.group ? this.selector.group : null);
		var newGroup = (selector.group ? selector.group : null);

		if (prevGroup !== newGroup) {
			
			var message = {
				"action": "setContext", 
				"context": {
					"group": newGroup
				}
			}
			console.log("------> Postmessage", message, $("iframe#connect-widget"));
			var ix = document.getElementById("connect-widget");
			if (ix) {
				ix.contentWindow.postMessage(message, '*');
			}
		}

		console.log("Set selector", selector);


		this.selector = selector;
		this.load();
	}


	FeedController.prototype.postEnable = function(e) {
		e.preventDefault();
		this.pane.el.find('#enablePost').hide();
		this.pane.el.find('#post').show();
	}

	FeedController.prototype.postDisable = function(e) {
		e.preventDefault();
		this.pane.el.find('#enablePost').show();
		this.pane.el.find('#post').hide();
	}

	FeedController.prototype.viewchange = function(opt) {
		console.log(' =============> View change', opt);

		this.pane.el.find('.feedcontainer').removeClass('view-' + this.view.view);
		this.pane.el.find('.feedcontainer').addClass('view-' + opt.view);

		this.view = opt;
		this.load();
	}




	FeedController.prototype.update = function() {
		var that = this;
		// console.log("About to update");
		if (!this.currentRange) return;
		// console.log("Updating...", this.currentRange);

		var s = this.getSettings();
		s.from = this.currentRange.to;


		UWAP.feed.read(s, function(data) {
			// console.log("FEED Update Received", data);
			// $(".feedtype").empty();
			if (!data.range) return;
			that.currentRange.to = data.range.to;
			$.each(data.items, function(i, item) {
				if (!item.hasOwnProperty('promoted')) {
					item.promoted = false;
				}
				that.addItem(item);
			});
			$("span.ts").prettyDate(); 
		});

	};

	FeedController.prototype.load = function() {
		var that = this;
		var 
			feedcontainer = this.pane.el.find('.feedcontainer');

		var s = this.getSettings();

		// console.log("Load ", this.view.view);
		if (this.view.view === 'members' && s.group) {
			

			// console.log("Load members", s);

			var gr = s.group;

			UWAP.groups.get(gr, function(data) {
				// console.log("Group data received.", data);

				if (data.userlist) {
					feedcontainer.empty();

					for(var uid in data.userlist) {
						feedcontainer.append('<div>' + data.userlist[uid]['name'] + '</div>');
					}

				}
				that.pane.activate();

				// $.each(data, function(i, item) {
				// 	that.addItem(item);
				// });
				// $("span.ts").prettyDate(); 
			}, function() {
				console.error("Could not get list");
			});

		} else {
			UWAP.feed.read(s, function(data) {
				// console.log("FEED Received", data);
				
				feedcontainer.empty();

				if (!data.range) return;
				that.currentRange = data.range;

				if (that.view.view === 'media') {
					feedcontainer.append('<ul></ul>');	
				}

				$.each(data.items, function(i, item) {
					if (item.inresponseto) return;
					that.addItem(item);
					
				});

				$.each(data.items, function(i, item) {
					if (!item.inresponseto) return;
					that.addItem(item);
				});

				// $.each(data.items, function(i, item) {
				// 	that.addItem(item);
				// });

				that.pane.activate();

				$("span.ts").prettyDate(); 
				$('.dropdown-toggle').dropdown();
			});

		}

		
	}
	FeedController.prototype.post = function(msg) {
		var that = this;
		// console.log("POSTING", msg);
		UWAP.feed.post(msg, function() {
			that.load();
		});
	}


	return FeedController;


});