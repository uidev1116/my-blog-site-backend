import type { UnitTreeNode, UnitTree, UnitList } from '@features/unit-editor/core';
import { parse } from 'qs';

export function flatten(items: UnitTree, parentId: UnitTreeNode['id'] | null = null) {
  return items.reduce((acc, item): UnitList => {
    const { children, ...itemWithoutChildren } = item;
    return [...acc, { ...itemWithoutChildren, parentId }, ...flatten(children ?? [], item.id)];
  }, [] as UnitList);
}

export function nestify(unitList: UnitList): UnitTree {
  const root = { id: 'root', children: [] };
  const nodes: Record<string, UnitTreeNode | Pick<UnitTreeNode, 'id' | 'children'>> = { [root.id]: root };
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const units = unitList.map(({ parentId: _, ...unit }) => ({ ...unit, children: [] as UnitTree }));

  for (const unit of units) {
    const { id, children } = unit;
    const parentId = unitList.find((u) => u.id === id)?.parentId ?? root.id;
    const parent = nodes[parentId] ?? units.find((unit) => unit.id === parentId);

    nodes[id] = { id, children };
    if (!Array.isArray(parent.children)) {
      parent.children = [];
    }
    parent.children.push(unit);
  }

  return root.children;
}

export function extract(element: Element) {
  function formDataToQueryString(formData: FormData) {
    const params = new URLSearchParams();
    for (const [key, value] of formData) {
      if (/\[[^\]]*\]$/.test(key)) {
        // []が含まれるname属性は、複数の値を持つ可能性があるため、appendを使用
        params.append(key, value as string);
      } else {
        // []が含まれないname属性は後から追加されたものを優先するため、appendではなくsetを使用
        params.set(key, value as string);
      }
    }
    return params.toString();
  }

  function parseFormData(formData: FormData) {
    const queryString = formDataToQueryString(formData);
    return parse(queryString);
  }

  const form = document.createElement('form');
  const elements = element.querySelectorAll<
    HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement
  >('input[name], select[name], textarea[name], button[name]');

  // 各要素をクローンしてformに追加（valueや選択状態を保持）
  elements.forEach((el) => {
    // file タイプの input は除外（input[type="file"]にvalueを設定すると、InvalidStateErrorが発生するため）
    if (el instanceof HTMLInputElement && el.type === 'file') {
      return;
    }
    if (el instanceof HTMLInputElement && (el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
      return; // チェックされていない checkbox/radio は FormData に含めない
    }

    const clone = el.cloneNode(true) as Element;

    // select要素はselectedIndexを同期させないと選択状態が失われる
    if (el instanceof HTMLSelectElement && clone instanceof HTMLSelectElement) {
      clone.selectedIndex = el.selectedIndex;
    }

    // checkbox, radio は checked の状態も反映が必要
    if (
      el instanceof HTMLInputElement &&
      clone instanceof HTMLInputElement &&
      (el.type === 'checkbox' || el.type === 'radio')
    ) {
      clone.checked = el.checked;
    }

    // input, textarea の value も反映（特に type="text"）
    if (
      (el instanceof HTMLInputElement && clone instanceof HTMLInputElement) ||
      (el instanceof HTMLTextAreaElement && clone instanceof HTMLTextAreaElement)
    ) {
      clone.value = el.value;
    }

    // 新しい要素を追加してMapに保存
    form.appendChild(clone);
  });

  const formData = new FormData(form);
  const data = parseFormData(formData);
  return data;
}
