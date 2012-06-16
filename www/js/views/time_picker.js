(function() {
    window.TimePickerView = Backbone.View.extend({
        events: {
            "click .time_picker_pre": "previous",
            "click .time_picker_post": "next"
        },

        initialize: function() {
            this.render();
            this.setTitle(this.options.title);
        },

        setTitle: function(title) {
            $(this.el).find(".time_picker_inner").html(title);
        },

        hide: function() {
            $(this.el).html("").hide();
        },

        render: function() {
            $(this.el)
                .html("<div class=\"time_picker_pre\"></div><div class=\"time_picker_inner\"></div><div class=\"time_picker_post\"></div>")
                .addClass("time_picker")
                .appendTo($("body"));
        },

        previous: function() {
            if (this.options.previousCB) {
                this.options.previousCB();
            }
        },

        next: function() {
            if (this.options.nextCB) {
                this.options.nextCB();
            }
        }
    });
})();
