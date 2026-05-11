import { render } from '../../utils/react';
import ModuleInfo from './module-info';

interface ModuleData {
  mid: number | string;
  moduleID: string;
  module: string;
  name: string;
  template: string;
  scope: string;
  preview: string;
  tpl_list: { tpl: string; label: string; selected?: string }[];
}

export function renderModuleInfo(moduleData: ModuleData, container: HTMLElement, onRendered?: () => void) {
  const tplEl = document.getElementById('js-module_info_tpl') as HTMLElement | null;
  const dataset = tplEl?.dataset ?? {};
  const labels = {
    emptyMessage: dataset.emptyMessage || '',
    globalLabel: dataset.globalLabel || '',
    duplicateLabel: dataset.duplicateLabel || '',
    settingsLabel: dataset.settingsLabel || '',
    selectLabel: dataset.selectLabel || '',
    previewLabel: dataset.previewLabel || '',
    previewScaleLabel: dataset.previewScaleLabel || '',
    defaultTpl: dataset.defaultTpl || '',
  };

  const component = <ModuleInfo module={moduleData} labels={labels} onMount={onRendered} />;

  const root = render(component, container);
  root.render(component);
}
