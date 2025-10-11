import htmx from 'htmx.org';

(function () {
  /**
   * リクエスト中かどうかを管理するフラグ
   */
  let isRequesting = false;
  htmx.defineExtension('acms-geolocation', {
    onEvent(name, event) {
      if (name === 'htmx:beforeRequest') {
        if (isRequesting) {
          // リクエスト中の場合は処理をスキップ
          return true;
        }
        const { requestConfig } = event.detail;

        event.preventDefault(); // 一旦リクエストをキャンセル

        ACMS.Library.geolocation(
          (latitude, longitude) => {
            const { formData } = requestConfig;
            // 緯度・経度の hidden フィールドを追加
            formData.append('lat', latitude.toString());
            formData.append('lng', longitude.toString());
            formData.append('query[]', 'lat');
            formData.append('query[]', 'lng');

            // 改めてリクエストを送信
            isRequesting = true;
            htmx
              .ajax(requestConfig.verb, requestConfig.path, {
                source: requestConfig.elt,
                event: requestConfig.triggeringEvent,
                target: requestConfig.target,
                values: requestConfig.parameters,
                headers: requestConfig.headers,
              })
              .finally(() => {
                // リクエストが完了したらフラグをリセット
                isRequesting = false;
              });
          },
          (message) => {
            window.alert(message);
          }
        );
      }
      return true;
    },
  });
})();
