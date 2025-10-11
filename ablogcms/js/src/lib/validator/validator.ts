import * as rules from './rules';
import { getFormElements, isFunction } from './utils';
import type { ValidationResult, Validation, ValidatorOptions, ValidationRule } from './types';

const defaultOptions: ValidatorOptions = {
  resultClassName: 'validator-result-',
  okClassName: 'valid',
  ngClassName: 'invalid',
  shouldScrollOnSubmitFailed: true,
  shouldFocusOnSubmitFailed: true,
  onInvalid: () => {},
  onValid: () => {},
  onValidated: () => {},
  onSubmitFailed: () => {},
  shouldValidate: 'onBlur',
  shouldValidateOnSubmit: true,
  shouldRevalidate: 'onChange',
  formnovalidateAttr: 'data-acms-formnovalidate',
  customRules: {},
};

class Validator {
  private form: HTMLFormElement;

  private config: ValidatorOptions;

  private rules: Record<string, ValidationRule>;

  private eventRegistry: Map<
    Element | Document,
    Array<{
      type: string;
      listener: EventListener;
      options?: AddEventListenerOptions;
    }>
  >;

  private invalidFields: Set<string>;

  private eventMap = {
    onBlur: ['focusout'],
    onChange: ['change', 'input'],
  };

  /**
   * constructor
   * @param form - The form element to validate
   * @param option - The options for the validator
   */
  constructor(form: HTMLFormElement, option: Partial<ValidatorOptions> = {}) {
    this.form = form;
    this.config = { ...defaultOptions, ...option };
    this.rules = { ...rules, ...this.config.customRules };
    this.eventRegistry = new Map();
    this.invalidFields = new Set();
    this.handleChange = this.handleChange.bind(this);
    this.handleSubmit = this.handleSubmit.bind(this);
    this.handleRevalidate = this.handleRevalidate.bind(this);
    this.register();
  }

  /**
   * register event listener
   * @returns {void}
   */
  public register(): void {
    if (this.config.shouldValidate !== false) {
      const eventNames = this.eventMap[this.config.shouldValidate];
      eventNames.forEach((eventName) => {
        // documentにイベントリスナーを登録
        document.addEventListener(eventName, this.handleChange);
        this.eventRegistry.set(document, [
          ...(this.eventRegistry.get(document) || []),
          { type: eventName, listener: this.handleChange },
        ]);
      });
    }

    // 失敗したフィールドの再バリデーション用のイベントリスナーを登録
    if (this.config.shouldRevalidate !== false) {
      const revalidateEventNames = this.eventMap[this.config.shouldRevalidate];
      revalidateEventNames.forEach((eventName) => {
        document.addEventListener(eventName, this.handleRevalidate);
        this.eventRegistry.set(document, [
          ...(this.eventRegistry.get(document) || []),
          { type: eventName, listener: this.handleRevalidate },
        ]);
      });
    }

    if (this.config.shouldValidateOnSubmit) {
      this.form.addEventListener('submit', this.handleSubmit);
      this.eventRegistry.set(this.form, [{ type: 'submit', listener: this.handleSubmit }]);
    }
  }

  /**
   * unregister event listener
   * @returns {void}
   */
  public unregister(): void {
    for (const [element, listeners] of this.eventRegistry) {
      listeners.forEach(({ type, listener, options }) => {
        element.removeEventListener(type, listener, options);
      });
    }
    this.eventRegistry.clear();
  }

  /**
   * バリデーションルールを登録する
   * @param name - ルールの名前
   * @param rule - ルールの関数
   */
  public registerRule(name: string, rule: ValidationRule): void {
    this.rules[name] = rule;
  }

  /**
   * バリデーションルールを削除する
   * @param name - ルールの名前
   */
  public unregisterRule(name: string): void {
    delete this.rules[name];
  }

  /**
   * バリデーションのイベントを再設定する
   * @returns {void}
   */
  public reset(): void {
    this.unregister();
    this.register();
  }

  /**
   * destroy
   * @returns {void}
   */
  public destroy(): void {
    this.unregister();
  }

  /**
   * handleChange
   * @param event - The event to handle
   */
  private handleChange(event: Event): void {
    if (
      !(event.target instanceof HTMLInputElement) &&
      !(event.target instanceof HTMLSelectElement) &&
      !(event.target instanceof HTMLTextAreaElement)
    ) {
      return;
    }

    if (this.config.shouldValidate === false) {
      return;
    }

    if (!this.getFields().includes(event.target)) {
      return;
    }

    this.checkValidity(event.target);
  }

  /**
   * handleRevalidate
   * @param event - The event to handle
   */
  private handleRevalidate(event: Event): void {
    if (
      !(event.target instanceof HTMLInputElement) &&
      !(event.target instanceof HTMLSelectElement) &&
      !(event.target instanceof HTMLTextAreaElement)
    ) {
      return;
    }

    if (this.config.shouldRevalidate === false) {
      return;
    }

    if (!this.getFields().includes(event.target)) {
      return;
    }

    // 失敗したフィールドの場合のみ再バリデーション
    if (!this.invalidFields.has(event.target.name)) {
      return;
    }
    this.checkValidity(event.target);
  }

  /**
   * handleSubmit
   * @param event - The event to handle
   */
  private handleSubmit(event: Event): void {
    const submitEvent = event as SubmitEvent;
    if (submitEvent?.submitter?.hasAttribute(this.config.formnovalidateAttr)) {
      return;
    }
    if (!this.config.shouldValidateOnSubmit) {
      return;
    }
    if (!this.checkFormValidity()) {
      event.preventDefault();

      if (this.config.shouldScrollOnSubmitFailed) {
        this.scrollToInvalidElement().then(() => {
          if (this.config.shouldFocusOnSubmitFailed) {
            this.focusInvalidElement();
          }
        });
      }
      if (!this.config.shouldScrollOnSubmitFailed && this.config.shouldFocusOnSubmitFailed) {
        this.focusInvalidElement();
      }
    }
  }

  /**
   * フォーム要素のすべての検証を行う
   * @returns {ok: boolean; validationResults: ValidationResult[]} - 検証結果
   */
  private validateAll(): { ok: boolean; validationResults: ValidationResult[] } {
    const fields = this.getFields().filter((field) => field.disabled === false); // disabledな要素はバリデーション対象外
    const names = [...new Set(fields.map((field) => field.name.replace(/\[[\d]*\]/, '')))];
    const nameToValidation = names.reduce<Record<string, Validation[]>>((obj, name) => {
      const validations = this.extractValidations(name);
      if (validations.length === 0) {
        return obj;
      }
      return { ...obj, [name]: validations };
    }, {});
    const allValidations = Object.values(nameToValidation)
      .flat()
      .filter((validation) => validation.rule.substring(0, 4) === 'all_');
    const validationResults: ValidationResult[] = [];
    allValidations.forEach((validation) => {
      const checkboxes = Array.from(this.getFields()).filter(
        (field) =>
          (field as HTMLInputElement).name === validation.field ||
          (field as HTMLInputElement).name === `${validation.field}[]`
      ) as HTMLInputElement[];
      const ok = this.rules[validation.rule]('', validation.arg, checkboxes, validation);
      this.label(validation.id, -1, ok);
      this.toggleClass(validation.field, ok);
      validationResults.push({
        ok,
        element: checkboxes,
        validation,
      });
    });
    fields.forEach((field) => {
      const validations = nameToValidation[field.name.replace(/\[[\d]*\]/, '')];
      if (typeof validations === 'undefined') {
        return;
      }
      const { ok, results } = this.validate(field, validations);
      this.toggleClass(field.name, ok);
      validationResults.push(...results);
    });
    const ok = validationResults.every((result) => result.ok);
    return {
      ok,
      validationResults,
    };
  }

  /**
   * フォーム要素の検証を行う
   * @param input - The input element to validate
   * @param validations - The validations to validate
   * @returns {ok: boolean; results: ValidationResult[]} - 検証結果
   */
  private validate(
    input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement,
    validations: Validation[]
  ): { ok: boolean; results: ValidationResult[] } {
    const matches = input.name.match(/\[([\d]+)\]/);
    const number = matches !== null ? parseInt(matches[1], 10) : -1;
    const results: ValidationResult[] = [];
    if (validations.length > 0) {
      validations.forEach((validation) => {
        if (!validation || !validation.rule) {
          return;
        }
        if (!isFunction(this.rules[validation.rule])) {
          return;
        }
        if (validation.rule.substring(0, 4) === 'all_') {
          return;
        }
        if (
          input instanceof HTMLInputElement &&
          (input.type === 'checkbox' || input.type === 'radio') &&
          validation.rule !== 'required'
        ) {
          return;
        }
        const ok = this.rules[validation.rule](input.value, validation.arg, input, validation);
        results.push({
          ok,
          element: input,
          validation,
        });
        this.label(validation.id, number, ok);
      });
    }
    const ok = results.every((result) => result.ok);
    return {
      ok,
      results,
    };
  }

  /**
   * フォームの検証を行う
   * @returns {boolean} - 検証結果
   */
  public checkFormValidity(): boolean {
    const { ok, validationResults } = this.validateAll();
    validationResults.forEach((result) => {
      if (Array.isArray(result.element)) {
        result.element.forEach((element) => {
          this.toggleAriaInvalid(element, result.ok);
          // 失敗フィールドを管理
          if (!result.ok) {
            this.invalidFields.add(element.name);
          } else {
            this.invalidFields.delete(element.name);
          }
        });
      } else {
        this.toggleAriaInvalid(result.element, result.ok);
        // 失敗フィールドを管理
        if (!result.ok) {
          this.invalidFields.add(result.element.name);
        } else {
          this.invalidFields.delete(result.element.name);
        }
      }
    });
    if (!ok) {
      this.config.onSubmitFailed(validationResults, this.form);
    }
    this.config.onValidated(validationResults, this.form);
    return ok;
  }

  /**
   * フォーム要素の検証を行う
   * @param input - The input element to validate
   * @returns {boolean} - 検証結果
   */
  public checkValidity(input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement): boolean {
    const validations = this.extractValidations(input.name);
    const { ok, results } = this.validate(input, validations);
    this.toggleClass(input.name, ok);
    this.toggleAriaInvalid(input, ok);

    // バリデーション結果に基づいて失敗フィールドを管理
    if (ok) {
      this.invalidFields.delete(input.name);
      this.config.onValid(results, input);
    } else {
      this.invalidFields.add(input.name);
      this.config.onInvalid(results, input);
    }

    this.config.onValidated(results, input);
    return ok;
  }

  /**
   * name属性に対するバリデーションを抽出する
   * @param name - The name of the input element to validate
   * @returns {Validation[]} - The validations
   */
  private extractValidations(name: string): Validation[] {
    const replaced = name.replace(/\[[\d]*\]/, '');
    const inputs = this.getFields()
      .filter(
        (element) => element.name.startsWith(`${replaced}:validator#`) || element.name.startsWith(`${replaced}:v#`)
      )
      .filter((element) => !element.disabled);
    return inputs.reduce<Validation[]>((validations, input) => {
      const element = input as HTMLInputElement;
      const matchs = element.name.match(/^(.*):(validator|v)#(.*)$/);
      if (matchs === null) {
        return validations;
      }

      return [
        ...validations,
        {
          field: matchs[1],
          rule: matchs[3] as keyof ValidationRule,
          arg: element.value,
          id: element.id,
          name: element.name,
        },
      ];
    }, []);
  }

  /**
   * フォームのフィールドを取得する
   * FieldSetElementはバリデーション対象外
   * @returns {(HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement)[]} - The fields
   */
  private getFields(): (HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | HTMLButtonElement)[] {
    // フォーム内のフィールドを取得
    return getFormElements(this.form);
  }

  /**
   * ラベル要素を更新する
   * @param id - ラベル要素のid
   * @param number - ラベル要素の番号
   * @param ok - ラベル要素の有効性
   */
  public label(id: string, number: number, ok: boolean): void {
    const { resultClassName } = this.config;
    const label = document.querySelectorAll(`[data-validator-label="${id}"]`);
    let target: Element | null = null;
    if (label.length < 1) {
      target = document.querySelector(`label[for="${id}"]`);
    }
    if (label.length === 1) {
      target = document.querySelector(`[data-validator-label="${id}"]`);
    }
    if (label.length > 1 && number > -1) {
      target = label[number];
    }
    if (target) {
      target.classList.remove(resultClassName);
      target.classList.remove(`${resultClassName}0`);
      target.classList.remove(`${resultClassName}1`);
      target.classList.add(`${resultClassName}${ok ? '1' : '0'}`);
    }
  }

  /**
   * クラスを切り替える
   * @param name - クラスを切り替える要素のname属性
   * @param ok - クラスを切り替える要素の有効性
   */
  public toggleClass(name: string, ok: boolean): void {
    const { okClassName, ngClassName } = this.config;
    const fields = this.getFields();
    const elements = fields
      .filter((field) => field.name === name || field.name === `${name}[]`)
      .concat(Array.from(document.querySelectorAll(`[data-validator="${name.replace(/\[[\d]*\]/, '')}"]`)));
    if (elements.length > 0) {
      elements.forEach((element) => {
        if (ok) {
          element.classList.remove(ngClassName);
          element.classList.add(okClassName);
        } else {
          element.classList.remove(okClassName);
          element.classList.add(ngClassName);
        }
      });
    }
  }

  /**
   * aria-invalid属性を切り替える
   * @param element - The element to toggle aria-invalid
   * @param ok - The validity of the element
   */
  private toggleAriaInvalid(element: HTMLElement, ok: boolean): void {
    if (
      element instanceof HTMLInputElement ||
      element instanceof HTMLSelectElement ||
      element instanceof HTMLTextAreaElement
    ) {
      element.setAttribute('aria-invalid', ok ? 'false' : 'true');
    }
  }

  /**
   * invalidな要素の内、最初の要素を返す
   * @returns {HTMLElement | null} - The invalid element
   */
  private getInvalidElement(): HTMLElement | null {
    const { ngClassName } = this.config;
    const fields = this.getFields();
    return fields.find((field) => field.classList.contains(ngClassName)) || null;
  }

  /**
   * 無効な要素までスクロールする
   * @returns {Promise<void>}
   */
  private scrollToInvalidElement(): Promise<void> {
    return new Promise((resolve) => {
      const element = this.getInvalidElement();
      if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
        let isScrolling: NodeJS.Timeout;

        const onScroll = () => {
          clearTimeout(isScrolling);
          isScrolling = setTimeout(() => {
            window.removeEventListener('scroll', onScroll);
            resolve();
          }, 100); // 100ms スクロールが停止したらスクロール終了と判断
        };

        window.addEventListener('scroll', onScroll);
      }
    });
  }

  /**
   * 無効な要素をフォーカスする
   * @returns {void}
   */
  private focusInvalidElement(): void {
    const element = this.getInvalidElement();
    if (element) {
      element.focus();
    }
  }
}

export default Validator;
