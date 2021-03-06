define(['./libs/moment', './LogRetriever'], function(moment, LogRetriever) {

	var LogApp = function(eloutput, elfilters) {
		var that = this;
		this.eloutput = eloutput;
		this.elfilters = elfilters;

		this.filters = [];

		this.from = null;
		this.to = null;


		this.eloutput.on("click", "div.logMain", function(event) {
			$(event.currentTarget).closest(".logentry").toggleClass("active");
			return false;
		});

		this.eloutput.on("click", "div.filterbtn ul.filterdropdown li", $.proxy(function(event) {
			var filter = $(event.currentTarget).data('filter');
			console.log("clicked fliter button ", event.currentTarget);
			console.log("Filter: ", filter);

			that.filters.push(filter);
			that.logr.setFilter(that.filters);
			that.listFilters();

			$(that.eloutput).empty();

			console.log("About to reset from ", that.from)
			that.logr.resetFrom(that.from);
			that.from = null;
		}, that));

		this.elfilters.on("click", "#resetfilters", function(event) {
			// that.logr.removeFilters();
			
			that.filters = [];
			that.logr.setFilter(that.filters);
			
			$(that.eloutput).empty();

			console.log("About to reset from ", that.from)
			that.logr.resetFrom(that.from);
			that.from = null;

			that.listFilters();
		});


		this.logr = new LogRetriever($.proxy(this.logresult, this));


		$(".tailcontrol").button()
			.on("click", "button.play", function() {

				console.log("Play");
				that.logr.play();
			})
			.on("click", "button.pause", function() {

				console.log("Pause");
				that.logr.pause();
			});

		// this.logr = new LogRetriever(this.logresult);
	};

	LogApp.prototype.listFilters = function() {
		var that = this;

		var filterlist = $(this.elfilters).find("#filterlist");

		filterlist.empty();

		// console.log("Listing filters");

		$.each(this.filters, function(i, filter) {
			for(var attribute in filter) {
				for(var value in filter[attribute]) {
					if (filter[attribute][value] === true) {
						filterlist.append('<div class="filter"><span class="label label-success">' + attribute + ' ' + value + '</span></div>');
					} else {
						filterlist.append('<div class="filter"><span class="label label-important">' + attribute + ' ' + value + '</span></div>');	
					}
					
				}
			}
			// console.log("Filter", filter);
			
		});

	}

	LogApp.prototype.logresult = function(logs) {
		var that = this;
		// console.log("Log files from " + moment.unix(logs.from).format('HH:mm:ss SSS') + ' to ' + moment.unix(logs.to).format('HH:mm:ss SSS') );
		// console.log(logs.data);

		if (!this.from) this.from = logs.from;
		if (!this.to) this.from = logs.to;
		if (logs.to > this.to) this.to = logs.to;

		$.each(logs.data, function(i, logentry) {
			var o =  '';
			var html, htmlo;

			if (logentry.object) {
				o = '<pre class="logobject">' + JSON.stringify(logentry.object, undefined, 4) + '</pre>';
			}


			html = '<div class="logentry level' + logentry.level + '">' + 
				'<div class="logMain"><span class="time">' + moment.unix(logentry.time).format('HH:mm:ss.SSS') + '</span> ' +
				'<span class="module">' + logentry.module + '</span> ' +
				'<span class="subid">' + logentry.subid + '</span> ';
			if (logentry.ip) {
				html += '<span class="ip">' + logentry.ip + '</span> ';
			}
			html += '<span class="host">' + logentry.host + '</span> ' +
				logentry.message + '</div>' +
				'<div class="logExtra"></div>' +
				'</div>';
			htmlo = $(html);
			htmlo.find('div.logExtra')
				.append(LogApp.filterBtn(logentry))
				.append(o);

			// console.log("el output", that.eloutput)
			$(that.eloutput).prepend(htmlo);
		});
	}

	LogApp.addFilter = function(filter) {


	};

	LogApp.filterBtn = function(o) {

		var ul = $('<ul class="filterdropdown dropdown-menu"></ul>');

		var props = ['module', 'ip', 'host', 'subid'];
		$.each(props, function(i, prop) {
			var li1, li2, f1, f2;

			if (!o[prop]) return;

			f1 = {}
			f1[prop] = {};
			f1[prop][o[prop]] = true;

			li1 = $('<li><a href="#">Restrict to only ' + prop + ' ' + o[prop] + '</a></li>').data('filter', f1);

			f2 = {}
			f2[prop] = {};
			f2[prop][o[prop]] = false;

			li2 = $('<li><a href="#">Exclude ' + prop + ' ' + o[prop] + '</a></li>').data('filter', f2);

			ul.append(li1).append(li2);
		});

		var html = $('<div class="filterbtn btn-group"><button class="btn btn-mini">Filter</button>' +
        '<button class="btn btn-mini dropdown-toggle" data-toggle="dropdown"><span class="caret"></span></button>' + 
		'</div>');

		html.append(ul);

		return html;

	}



	return LogApp;
});