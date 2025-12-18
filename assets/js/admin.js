(function ($) {
  "use strict";

  $(document).ready(function () {
    // Генерация изображения
    $("#aigfp_generate_btn").on("click", function () {
      var $btn = $(this);
      var $loading = $("#aigfp_loading");
      var $loadingTxt = $("#aigfp_loading_txt");
      var $result = $("#aigfp_result");
      var $preview = $("#aigfp_preview");
      var $useBtn = $("#aigfp_use_image");

      var prompt =
        $("#aigfp_prompt").val() +
        "\n" +
        $("#aigfp_style option:selected").text();
      var model = $("#aigfp_model").val();
      var quality = $("#aigfp_quality").val();
      var postId = $("#post_ID").val();

      // Показываем индикатор загрузки
      $btn.prop("disabled", true);
      $loadingTxt.text(aigfp_ajax.i18n.generating_image);
      $loading.show();
      $result.hide();
      $useBtn.hide();

      // Используем Puter.js для генерации изображения
      if (typeof puter !== "undefined") {
        puter.ai
          .txt2img(prompt, {
            model: model,
            quality: quality,
          })
          .then(function (image) {
            // Получаем Data URL изображения
            var imageDataURL = image.src;

            // Сохраняем в медиабиблиотеку WordPress
            saveImageToWordPress(imageDataURL, postId, prompt)
              .then(function (attachment) {
                console.log(attachment);

                // Показываем превью
                $preview.html(
                  '<img src="' +
                    attachment.url +
                    '" style="max-width:100%; height:auto;">'
                );
                $result.show();

                // Показываем кнопку Use Image
                $useBtn.data("attachment-id", attachment.id);
                $useBtn.show();
              })
              .catch(function (error) {
                alert(
                  "❌ " + aigfp_ajax.i18n.error_saving + "\n" + error.message
                );
              })
              .finally(function () {
                // Прячем лоадер
                $loading.hide();
                // Активируем кнопку Generate Image
                $btn.prop("disabled", false);
              });

            $loadingTxt.text(aigfp_ajax.i18n.uploading_image);
          })
          .catch(function (error) {
            var $errorMsg =
              error && error.message
                ? error.message
                : aigfp_ajax.i18n.error_funds;
            alert("❌ " + aigfp_ajax.i18n.error_generic + " \n" + $errorMsg);
            $loading.hide();
            $btn.prop("disabled", false);
          });
      } else {
        alert(aigfp_ajax.i18n.error_sdk);
        $loading.hide();
        $btn.prop("disabled", false);
      }
    });

    // Использование изображения как миниатюры
    $("#aigfp_use_image").on("click", function () {
      var attachmentId = $(this).data("attachment-id");
      var postId = $("#post_ID").val();

      if (attachmentId) {
        // Устанавливаем как featured image
        $.ajax({
          url: aigfp_ajax.ajax_url,
          type: "POST",
          data: {
            action: "aigfp_set_featured_image",
            nonce: aigfp_ajax.nonce,
            post_id: postId,
            attachment_id: attachmentId,
          },
          success: function (response) {
            if (response.success) {
              // Обновляем миниатюру в интерфейсе WordPress
              $("#postimagediv .inside").html(response.data.html);
              alert("✅ " + aigfp_ajax.i18n.success_featured);
              $("#aigfp_result").hide();
              $("#aigfp_use_image").hide();
            } else {
              alert(
                "❌ " + aigfp_ajax.i18n.error_generic + " \n" + response.data
              );
            }
          },
        });
      }
    });

    // Функция сохранения изображения в медиабиблиотеку
    function saveImageToWordPress(imageDataURL, postId, prompt) {
      return new Promise(function (resolve, reject) {
        // Конвертируем Data URL в Blob
        var blob = dataURLtoBlob(imageDataURL);
        var formData = new FormData();

        formData.append("action", "aigfp_upload_image");
        formData.append("nonce", aigfp_ajax.nonce);
        formData.append("post_id", postId);
        formData.append(
          "image",
          blob,
          "ai-generated-image-" + Date.now() + ".png"
        );
        formData.append("prompt", prompt);

        $.ajax({
          url: aigfp_ajax.ajax_url,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          success: function (response) {
            if (response.success) {
              resolve(response.data);
            } else {
              reject(response.data);
            }
          },
          error: function (xhr, status, error) {
            reject(error);
          },
        });
      });
    }

    // Вспомогательная функция: Data URL → Blob
    function dataURLtoBlob(dataURL) {
      var arr = dataURL.split(",");
      var mime = arr[0].match(/:(.*?);/)[1];
      var bstr = atob(arr[1]);
      var n = bstr.length;
      var u8arr = new Uint8Array(n);

      while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
      }

      return new Blob([u8arr], { type: mime });
    }
  });
})(jQuery);
