import { lazy, Suspense } from 'react';
import type { UnitTree, UnitTreeNode } from '@features/unit-editor/core/types/unit';
import { type Editor } from '@features/unit-editor/core';
import type { UnitEditorSettings } from '@features/unit-editor/types';
import { defaultEditorSettings } from '@features/unit-editor/config';
import Spinner from '@components/spinner/spinner';
import { render } from '../utils/react';

function getDefaultSummaryRange(): number | null {
  const input = document.querySelector<HTMLInputElement>('[name="summary_range"]');
  if (input == null) {
    return null;
  }
  if (input.value === '') {
    return null;
  }
  return parseInt(input.value, 10);
}

function setDefaultHtml(tree: UnitTree): UnitTree {
  const template = document.querySelector<HTMLTemplateElement>('#unit-html-repository');
  if (template) {
    const clone = template.content.cloneNode(true) as HTMLElement;
    const nodes = clone.querySelectorAll(`[data-unit-id]`);
    if (nodes.length === 0) {
      return tree;
    }
    nodes.forEach((node) => {
      const html = node.outerHTML;
      const findUnit = (units: UnitTree, id: UnitTreeNode['id']): UnitTreeNode | undefined => {
        for (const unit of units) {
          if (unit.id === id) {
            return unit;
          }
          if (unit.children.length > 0) {
            const found = findUnit(unit.children, id);
            if (found) {
              return found;
            }
          }
        }
        return undefined;
      };
      const id = node.getAttribute('data-unit-id')!;
      const unit = findUnit(tree, id);
      if (unit) {
        unit.defaultHtml = html;
      }
    });
  }
  return tree;
}

export function getDefaultValue(element: HTMLElement): UnitTree {
  const json = element.getAttribute('data-default-value') ?? '[]';
  const tree: UnitTree = JSON.parse(json);

  const data = setDefaultHtml(tree);

  return data;
}

export function getSettings(element: HTMLElement): UnitEditorSettings {
  const json = element.getAttribute('data-settings') ?? '{}';
  const settings: Partial<UnitEditorSettings> = JSON.parse(json);
  return { ...defaultEditorSettings, ...settings };
}

function applySummaryRange(value: string) {
  const input = document.querySelector<HTMLInputElement>('[name="summary_range"]');
  if (input) {
    input.value = value;
  }
}

export default function dispatchUnitEditor(context: Element | Document = document) {
  const elements = context.querySelectorAll<HTMLElement>(ACMS.Config.unitEditorMark);
  if (elements.length === 0) {
    return;
  }
  const UnitEditor = lazy(
    () => import(/* webpackChunkName: "unit-editor" */ '../features/unit-editor/components/unit-editor')
  );

  const handleContentChange = (data: UnitTree) => {
    const index = data.findIndex((unit) => unit.type === 'more');
    if (index === -1) {
      return applySummaryRange('');
    }
    applySummaryRange(index.toString());
  };

  elements.forEach((element) => {
    const defaultValue = getDefaultValue(element);
    const settings = getSettings(element);
    const handleCreate = (editor: Editor) => {
      (element as any).unitEditor = editor; // eslint-disable-line @typescript-eslint/no-explicit-any

      const summaryRange = getDefaultSummaryRange();
      if (summaryRange !== null) {
        const unit = editor.createUnit('more');
        editor.commands.insertUnit(unit, { index: summaryRange });
      }
    };
    render(
      <Suspense
        fallback={
          <div className="acms-admin-d-flex acms-admin-justify-content-center acms-admin-align-items-center">
            <Spinner size={20} />
          </div>
        }
      >
        <UnitEditor
          defaultValue={defaultValue}
          settings={settings}
          onCreate={handleCreate}
          onContentChange={handleContentChange}
        />
      </Suspense>,
      element
    );
  });
}
