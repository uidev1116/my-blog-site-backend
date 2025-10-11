import { useSortable } from '@dnd-kit/sortable';
import type Editor from '../editor';
import { UnitConfigEditProps } from './config';

/**
 * ユニットのステータス
 */
export type UnitStatus = 'open' | 'close';

/**
 * ユニットの配置タイプ
 *
 * 'auto' - 自動配置
 * 'center' - 中央配置 or 全体配置
 * 'left' - 左配置
 * 'right' - 右配置
 */
export type UnitAlign = 'auto' | 'center' | 'left' | 'right';

export type UnitAlignOption = {
  value: UnitAlign;
  label: string;
};

/**
 * ユニットの属性値として使用可能な型
 * フォームのHTML文字列を受け取ることが可能。HTML文字列の場合は、フォームの値を解析して、オブジェクトに変換する。
 */
type UnitAttributes = Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

/**
 * ユニットの型定義
 */
export interface UnitInterface<T extends UnitAttributes = UnitAttributes> {
  /**
   * ユニットID
   */
  id: string;

  /**
   * ユニット名
   */
  name: string;

  /**
   * ユニットの種類
   */
  type: string;

  /**
   * ユニットのステータス
   */
  status: UnitStatus;

  /**
   * 配置タイプ
   */
  align?: UnitAlign;

  /**
   * グループ
   * @deprecated ユニットグループは非推奨です。代わりにグループユニットを利用してください
   */
  group?: string;

  /**
   * 折りたたみ状態
   */
  collapsed: boolean;

  /**
   * アンカー
   */
  anker?: string;

  /**
   * ユニットのデータ
   */
  attributes: T;

  /**
   * 初回表示に利用するユニットのHTML
   */
  defaultHtml?: string;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface UnitListItem<T extends UnitAttributes = UnitAttributes> extends UnitInterface<T> {
  parentId: UnitInterface<T>['id'] | null;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface UnitTreeNode<T extends UnitAttributes = UnitAttributes> extends UnitInterface<T> {
  children: UnitTree<T>;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type UnitList<T extends UnitAttributes = UnitAttributes> = UnitListItem<T>[];

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type UnitTree<T extends UnitAttributes = UnitAttributes> = UnitTreeNode<T>[];

/**
 * ユニットメニューの定義
 */
export interface UnitMenuItem {
  /**
   * メニューのID
   */
  id: string;

  /**
   * メニューのラベル
   */
  label: string;

  /**
   * メニューのアイコン
   */
  icon?: string;

  /**
   * メニューのカテゴリ
   */
  category?: {
    slug: string;
    name: string;
  };

  /**
   * 追加対象のユニット定義名の配列
   */
  units: {
    id: UnitTreeNode['type'];
    name: UnitTreeNode['name'];
    align?: UnitTreeNode['align'];
    group?: UnitTreeNode['group'];
    attributes: UnitTreeNode['attributes'];
  }[];
}

export type UnitAlignVersion = 'v1' | 'v2';

/**
 * ユニットタイプの定義
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface UnitDefInterface<T extends UnitAttributes = any> {
  type: UnitInterface['type'];
  name: string;
  icon?: string;
  edit: (props: UnitEditProps<T>) => React.ReactNode;
  config?: (props: UnitConfigEditProps) => React.ReactNode;
  inplaceEdit?: (props: UnitInplaceEditProps<T>) => React.ReactNode;
  supports?: {
    /**
     * 複数配置可能かどうか
     * @default true
     */
    multiple?: boolean;

    /**
     * ネスト可能かどうか（親子関係の子になれるかどうか）
     * @default true
     */
    nested?: boolean;

    /**
     * 配置タイプ
     * @default []
     */
    align?: UnitAlignOption[] | ((version: UnitAlignVersion) => UnitAlignOption[]);

    /**
     * 複製可能かどうか
     * @default true
     */
    duplicate?: boolean | ((unit: UnitTreeNode<T>, editor: Editor) => boolean);

    /**
     * 階層間での移動が可能かどうか
     * @default true
     */
    moveHierarchy?: boolean | ((unit: UnitTreeNode<T>, editor: Editor) => boolean);
  };
}

type UseSortableReturn = ReturnType<typeof useSortable>;

export type HandleProps = {
  ref: UseSortableReturn['setActivatorNodeRef'];
} & NonNullable<UseSortableReturn['attributes']>;

export interface UnitEditProps<T extends UnitAttributes = UnitAttributes> {
  unit: UnitTreeNode<T>;
  editor: Editor;
  handleProps: HandleProps;
}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
export interface UnitInplaceEditProps<T extends UnitAttributes = UnitAttributes>
  extends Omit<UnitEditProps<T>, 'handleProps'> {}

export interface UnitPosition {
  index: number;
  rootId?: UnitTreeNode['id'];
}

export interface UnitLastPosition extends Omit<UnitPosition, 'index'> {
  index: undefined;
}
