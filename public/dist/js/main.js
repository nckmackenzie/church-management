$(function () {
  $("#register").validate({
    rules: {
      name: {
        required: true,
      },
    },
    messages: {
      name: {
        required: "User Id Required",
      },
    },
    errorElement: "span",
    errorPlacement: function (error, element) {
      error.addClass("invalid-feedback");
      element.closest(".form-group").append(error);
    },
    highlight: function (element, errorClass, validClass) {
      $(element).addClass("is-invalid");
    },
    unhighlight: function (element, errorClass, validClass) {
      $(element).removeClass("is-invalid");
    },
  });
});
// alert("Something");
