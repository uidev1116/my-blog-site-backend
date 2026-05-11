import { useEffect } from 'react';

interface TplItem {
  tpl: string;
  label: string;
  selected?: string;
}

interface ModuleData {
  mid: number | string;
  moduleID: string;
  module: string;
  name: string;
  template: string;
  scope: string;
  preview: string;
  tpl_list: TplItem[];
}

interface ModuleInfoLabels {
  emptyMessage: string;
  globalLabel: string;
  duplicateLabel: string;
  settingsLabel: string;
  selectLabel: string;
  previewLabel: string;
  previewScaleLabel: string;
  defaultTpl: string;
}

interface ModuleInfoProps {
  module: ModuleData;
  labels: ModuleInfoLabels;
  onMount?: () => void;
}

const ModuleInfo = ({ module: mod, labels, onMount }: ModuleInfoProps) => {
  useEffect(() => {
    onMount?.();
  }, [onMount]);

  if (mod.mid === 0 || mod.mid === '') {
    return (
      <p className="acms-admin-alert acms-admin-alert-icon acms-admin-alert-info">
        <span className="acms-admin-icon acms-admin-icon-news acms-admin-alert-icon-before" aria-hidden="true" />
        {labels.emptyMessage}
      </p>
    );
  }

  if (!(Number(mod.mid) > 0)) {
    return null;
  }

  return (
    <>
      <header>
        <div className="acms-admin-module-title-sub clearfix">
          <p className="acms-admin-m-0 acms-admin-float-left">
            <span className="acms-admin-label acms-admin-margin-right-small">{mod.mid}</span>
            {mod.moduleID}
          </p>
          <p className="acms-admin-m-0 acms-admin-float-right">{mod.module}</p>
        </div>
        <div className="acms-admin-module-title-main clearfix">
          <h1 className="acms-admin-module-title acms-admin-float-left">{mod.name}</h1>
          <ul className="acms-admin-list-nostyle acms-admin-m-0 acms-admin-float-right">
            {mod.template.length > 0 && (
              <li className="acms-admin-margin-left-small acms-admin-float-left">
                <span className="acms-admin-label">{mod.template}</span>
              </li>
            )}
            {mod.scope === 'global' && (
              <li className="acms-admin-margin-left-small acms-admin-float-left">
                <span className="acms-admin-label acms-admin-label-info">{labels.globalLabel}</span>
              </li>
            )}
          </ul>
        </div>
      </header>

      <div className="acms-admin-margin-bottom-mini">
        <div className="acms-admin-float-right acms-admin-position-right">
          <button type="button" className="js-acms_layout_dup_module acms-admin-btn-admin" data-mid={mod.mid}>
            {labels.duplicateLabel}
          </button>{' '}
          <button type="button" className="js-acms_layout_edit_module acms-admin-btn-admin" data-mid={mod.mid}>
            {labels.settingsLabel}
          </button>
        </div>

        <form
          id="module-template-form"
          className="acms-admin-form-inline"
          action=""
          method="post"
          encType="multipart/form-data"
        >
          <button type="submit" className="acms-admin-btn-admin acms-admin-btn-admin-primary">
            {labels.selectLabel}
          </button>
          <input type="hidden" name="mid" value={String(mod.mid)} />
        </form>

        <select
          name="template"
          className="js-acms_layout_select_template js-select2"
          data-mid={mod.mid}
          data-default-tpl={labels.defaultTpl || ''}
          form="module-template-form"
          defaultValue={mod.tpl_list.find((tpl) => tpl.selected)?.tpl}
        >
          {mod.tpl_list.map((tpl) => (
            <option key={tpl.tpl} value={tpl.tpl}>
              {tpl.label}
            </option>
          ))}
        </select>
      </div>

      <h3 className="acms-admin-admin-title2">
        {labels.previewLabel} ({labels.previewScaleLabel})
      </h3>
      <div className="acms-admin-layout-module-edit-inner acms-admin-padding-small">
        {/* preview is sanitized HTML from CMS backend module renderer, not user input */}
        <div
          style={{ transform: 'scale(0.75)', transformOrigin: '0 0', width: '133.3%' }}
          dangerouslySetInnerHTML={{ __html: mod.preview }} // eslint-disable-line react/no-danger
        />
      </div>
    </>
  );
};

export default ModuleInfo;
