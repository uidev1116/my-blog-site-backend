import * as rules from './rules';

/**
 * バリデーターの設定オプション
 */
export interface ValidatorOptions {
  /**
   * バリデーション結果のクラス名のプレフィックス
   */
  resultClassName: string;
  /**
   * バリデーション成功時に付与されるクラス名
   */
  okClassName: string;
  /**
   * バリデーション失敗時に付与されるクラス名
   */
  ngClassName: string;
  /**
   * バリデーション失敗時にスクロールするかどうか
   */
  shouldScrollOnSubmitFailed: boolean;
  /**
   * バリデーション失敗時に要素をフォーカスするかどうか
   */
  shouldFocusOnSubmitFailed: boolean;
  /**
   * 送信時のバリデーション失敗時のコールバック関数
   */
  onSubmitFailed: (results: ValidationResult[], form: HTMLFormElement) => void;
  /**
   * バリデーション成功時のコールバック関数
   */
  onValid: (results: ValidationResult[], element: HTMLElement) => void;
  /**
   * バリデーション失敗時のコールバック関数
   */
  onInvalid: (results: ValidationResult[], element: HTMLElement) => void;
  /**
   * バリデーション後に実行されるコールバック関数
   */
  onValidated: (results: ValidationResult[], element: HTMLElement) => void;
  /**
   * バリデーションのタイミング
   */
  shouldValidate: 'onBlur' | 'onChange' | false;

  /**
   * 再バリデーションのタイミング
   */
  shouldRevalidate: 'onBlur' | 'onChange' | false;

  /**
   * 送信時のバリデーションを実行するかどうか
   */
  shouldValidateOnSubmit: boolean;

  /**
   * 送信時のバリデーションを実行させないSubmitに付ける属性
   */
  formnovalidateAttr: string;

  /**
   * カスタムバリデーションルール
   */
  customRules: Record<string, ValidationRule>;
}

/**
 * バリデーションルールの定義
 */
export interface Validation {
  /**
   * バリデーション対象のフィールド名
   */
  field: string;
  /**
   * 適用するバリデーションルール
   */
  rule: keyof typeof rules;
  /**
   * バリデーションルールの引数
   */
  arg: string;
  /**
   * バリデーション要素のid属性
   */
  id: string;
  /**
   * バリデーション要素のname属性
   */
  name: string;
}

/**
 * バリデーション結果
 */
export interface ValidationResult {
  /**
   * バリデーションが成功したかどうか
   */
  ok: boolean;
  /**
   * バリデーション対象の要素
   */
  element:
    | HTMLInputElement
    | HTMLSelectElement
    | HTMLTextAreaElement
    | HTMLButtonElement
    | (HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement)[];
  /**
   * 適用されたバリデーションルール
   */
  validation: Validation;
}

/**
 * バリデーションルールの型定義
 * @param val - 検証する値
 * @param arg - バリデーションルールの引数
 * @param input - 検証対象のHTML要素
 * @param v - バリデーションルールの定義
 * @returns バリデーション結果（true: 成功、false: 失敗）
 */
export type ValidationRule = (
  val: string,
  arg: string,
  input:
    | HTMLInputElement
    | HTMLSelectElement
    | HTMLTextAreaElement
    | HTMLButtonElement
    | (HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement)[],
  v: Validation
) => boolean;
