function initRepairGet() {
  var o = {
    get: function() {
      return this.name + '=' + this.value;
    },
    onSuccess: function() {
      var q = 'count=1&product_no=' + $E('product_no').value;
      send(WEB_URL + 'index.php/repair/model/autocomplete/find', q, function(xhr) {
        var datas = xhr.responseText.toJSON();
        if (datas) {
          topic.valid().value = datas[0].topic;
          product_no.valid().value = datas[0].product_no;
        } else {
          topic.invalid();
          product_no.invalid();
        }
      });
    },
    onChanged: function() {
      topic.reset();
      product_no.reset();
    }
  };
  var topic = initAutoComplete(
    'topic',
    WEB_URL + 'index.php/repair/model/autocomplete/find',
    'topic,product_no',
    'find',
    o
  );
  var product_no = initAutoComplete(
    'product_no',
    WEB_URL + 'index.php/repair/model/autocomplete/find',
    'product_no,topic',
    'find',
    o
  );
}

function initRepairDownload(id) {
  var doDelete = function() {
    if (confirm(trans("You want to XXX ?").replace(/XXX/, trans("delete")))) {
      send(
        "index.php/repair/model/detail/action",
        "id=" + this.id,
        doFormSubmit,
        this
      );
    }
  };
  forEach($G(id).elems("a"), function() {
    if (/^delete_([a-z0-9]+)$/.test(this.id)) {
      callClick(this, doDelete);
    }
  });
}
