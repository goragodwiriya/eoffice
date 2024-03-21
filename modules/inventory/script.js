function initInventoryWrite() {
  barcodeEnabled(["serial"]);
  initAutoComplete(
    "device_user",
    WEB_URL + "index.php/index/model/autocomplete/findUser",
    "name,phone,id_card",
    "user", {
      get: function() {
        return (
          "name=" +
          encodeURIComponent($E("device_user").value) +
          "&from=name,phone,id_card"
        );
      },
      callBack: function() {
        $E("member_id").value = this.id;
        $G("device_user").valid().value = this.name.unentityify();
      },
      onChanged: function() {
        $E("member_id").value = 0;
        $G("device_user").reset();
      }
    }
  );
}
