(function() {
    window.TL_Data_Object = Backbone.Model.extend({
        idAttribute: '__key'
    });

    window.TL_Data_Index = function(name, value) {
        this.name = name;
        this.value = value;
        this.active = false;
        this.refCount = 0;
        this.latestTS = 0;
        this.models = [];
        this.values = {};
    }

    _.extend(TL_Data_Index.prototype, Backbone.Events, {
        addRef: function() {
            this.refCount++;
            if (!this.active)
                this.active = true;
            return this;
        },

        removeRef: function() {
            if (this.refCount > 0)
                this.refCount--;
            if (this.refCount == 0)
                this.active = false;
            return this;
        },

        updateIndex: function(timestamp, entries) {
            var reset = false;
            if (this.latestTS == 0)
                reset = true;

            this.latestTS = timestamp;
            var self = this;
            _.each(entries, function(entry) {
                var key = entry.key;
                var value = entry.value;
                var model = TL_DataStore.getObject(key);

                self.models.push(model);

                self.values[value] = self.values[value] || [];
                self.values[value].push(model);

                if (!reset)
                    self.trigger('add', model);
            });
            if (reset)
                this.trigger('reset');
        }
    });

    window.TL_Data_Sum = function(name, value) {
        this.name = name;
        this.value = value;
        this.active = false;
        this.refCount = 0;
        this.latestTS = 0;
        this.values = {};
    }

    _.extend(TL_Data_Sum.prototype, Backbone.Events, {
        addRef: function() {
            this.refCount++;
            if (!this.active)
                this.active = true;
            return this;
        },

        removeRef: function() {
            if (this.refCount > 0)
                this.refCount--;
            if (this.refCount == 0)
                this.active = false;
            return this;
        },

        updateSums: function(timestamp, entries) {
            var reset = false;
            if (this.latestTS == 0) {
                reset = true;
            }
            this.latestTS = timestamp;
            var self = this;
            _.each(entries, function(entry, key) {
                if (!self.values[key] || self.values[key] != entry) {
                    self.values[key] = entry;

                    if (!reset)
                        self.trigger('add', entry);
                }
            });
            if (reset)
                this.trigger('reset');
        }
    });

    window.TL_DataStore = {
        objects: {}, // TL_Data_Object objects
        indices: {}, // TL_Data_Index objects
        sums: {}, // TL_Data_Sum objects
        puts: [], // TL_Data_Put objects

        pokeTimeout: null,

        clearAll: function() {
            this.objects = {};
            this.indices = {};
            this.puts = [];
        },

        createObject: function(key, type, data, callback) {
            this.puts.push({
                key: key,
                type: type,
                data: data,
                callback: callback,
                isUpdate: false
            });
        },

        updateObject: function(object, data, callback) {
            this.puts.push({
                key: object.get("__key"),
                type: object.get("__type"),
                data: data,
                callback: callback,
                isUpdate: true
            });
        },

        poke: function() {
            if (TL_DataStore.pokeTimeout) {
                clearTimeout(TL_DataStore.pokeTimeout);
            }
            // Wait for a short while to catch multiple back-to-back requests
            TL_DataStore.pokeTimeout = setTimeout(TL_DataStore.sendPoke, 100);
        },

        sendPoke: function() {
            if (TL_DataStore.pokeInProgress) {
                TL_DataStore.queuedPoke = true;
                return;
            }

            activeIndices = [];
            activeSums = [];

            data = {
                indices: [],
                sums: [],
                puts: []
            };

            _.each(TL_DataStore.indices, function(index) {
                if (index.active) {
                    data.indices.push({'index':index.name,'value':index.value,'ts':index.latestTS});
                    activeIndices.push(index);
                }
            });
            _.each(TL_DataStore.sums, function(sum) {
                if (sum.active) {
                    data.sums.push({'sum':sum.name,'value':sum.value,'ts':sum.latestTS});
                    activeSums.push(sum);
                }
            });

            data.puts = TL_DataStore.puts;
            TL_DataStore.puts = [];

            TL_DataStore.pokeInProgress = true;
            $.ajax({
                url: '/script/poke.php',
                data: {data:JSON.stringify(data)},
                dataType: 'json',
                success: function(json) {
                    TL_DataStore.updateObjects(json.objects);

                    _.each(activeIndices, function(index) {
                        index.updateIndex(json.ts, json.indices[index.name + '=' + index.value]);
                    });
                    _.each(activeSums, function(sum) {
                        sum.updateSums(json.ts, json.sums[sum.name + '=' + sum.value]);
                    });

                    _.each(data.puts, function(put) {
                        if (put.callback) {
                            put.callback();
                        }
                    });

                    TL_DataStore.pokeInProgress = false;

                    if (TL_DataStore.queuedPoke) {
                        TL_DataStore.queuedPoke = false;
                        TL_DataStore.poke();
                    } else {
                        // Reset for 10 seconds from now
                        TL_DataStore.pokeTimeout = setTimeout(TL_DataStore.sendPoke, 10000);
                    }
                },
                error: function() {
                    // TODO: Handle error
                }
            });
        },

        getObject: function(key) {
            key = key.toLowerCase();
            return TL_DataStore.objects[key];
        },
        getIndex: function(name, value) {
            var key = name + '=' + value;
            if (!TL_DataStore.indices[key])
                TL_DataStore.indices[key] = new TL_Data_Index(name, value);
            return TL_DataStore.indices[key];
        },
        getSum: function(name, value) {
            var key = name + '=' + value;
            if (!TL_DataStore.sums[key])
                TL_DataStore.sums[key] = new TL_Data_Sum(name, value);
            return TL_DataStore.sums[key];
        },

        updateObjects: function(objects) {
            $.each(objects, function(type, list) {
                $.each(list, function(key, value) {
                    value.__key = key;
                    value.__type = type;
                    // Hack for "private_to" field
                    if (value.private_to && value.private_to != TL_Auth.currentUser) {
                        value.description = '** PRIVATE **';
                        value.location = '';
                        value.comments = '** PRIVATE **';
                    }
                    key = key.toLowerCase();
                    if (!TL_DataStore.objects[key]) {
                        TL_DataStore.objects[key] = new TL_Data_Object(value);
                    } else {
                        TL_DataStore.objects[key].set(value);
                    }
                });
            });
        },

        generateKey: function(prefix) {
            var domain = TL_Auth.currentDomain;
            var time = new Date().getTime();
            return domain + ':' + prefix + time.toString(32);
        },
    }

})();
