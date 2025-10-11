interface Unit extends JQuery<HTMLElement> {
  $target: JQuery<HTMLElement>;
}
//-----------
// Edit._add
export default ($unit: Unit) => {
  // targetがなければ実行しない
  if ($unit.$target.length === 0) {
    return false;
  }

  const { Edit } = ACMS.Dispatch;
  const url = ACMS.Library.acmsLink({ tpl: 'ajax/unit/form/menu.json' }, true);

  $.getJSON(url, (data) => {
    $.each(data.type, (i, type) => {
      let icon = '';
      let className = '';
      if (data.icon && data.icon[i]) {
        icon = data.icon[i];
      }
      if (data.className && data.className[i]) {
        className = data.className[i];
      }
      const $input = $(
        $.parseHTML(`<div class="acms-admin-inline-btn">
        <button type="button" aria-label="${data.label[i]}${ACMS.i18n('entry_editor.add_unit')}" class="${className || 'acms-admin-btn-admin'}">
          ${icon ? `<span class="${icon}"></span>` : ''}
          ${data.label[i]}
        </button>
      </div>`)
      );
      $unit.$target.find('.buttonlist').append(...$input);

      $input.on('click', () => {
        const tpl = 'ajax/unit/form/add.html';
        const url = ACMS.Library.acmsLink(
          {
            tpl,
            admin: `entry-add-${type}`,
            Query: {
              hash: Math.random().toString(),
              limit: $unit.find(ACMS.Config.unitFormEditorItemMark).length.toString(),
            },
          },
          true
        );

        $.get(url, (html) => {
          if (!html) {
            return;
          }

          const $newItem = $(ACMS.Config.unitFormEditorItemMark, html) as unknown as JQuery<HTMLElement>;
          $newItem.hide();
          $unit.$target.before($newItem);
          $newItem.fadeIn();
          // $item 内 itemBodyMarkの最初のフォーム要素にフォーカスを当てる
          $newItem.find(`${ACMS.Config.unitFormEditorItemBodyMark} :input`).first().trigger('focus');

          Edit._refresh($unit);

          //---------------
          // dispatch item
          $newItem.each(function () {
            Edit._item(this, $unit);
          });
        });
      });
    });
  });
};
