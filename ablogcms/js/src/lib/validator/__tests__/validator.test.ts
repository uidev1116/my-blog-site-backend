import { describe, test, expect, beforeEach, afterEach, vi } from 'vitest';
import Validator from '../validator';

describe('Validator', () => {
  let form: HTMLFormElement;
  let validator: Validator;

  beforeEach(() => {
    form = document.createElement('form');
    form.id = 'test-form';
    document.body.appendChild(form);
  });

  afterEach(() => {
    document.body.removeChild(form);
    if (validator) {
      validator.destroy();
    }
  });

  test('should create validator instance', () => {
    validator = new Validator(form);
    expect(validator).toBeInstanceOf(Validator);
  });

  describe('ValidatorOptions', () => {
    test('should use custom resultClassName', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      validationRule.id = 'username-required';
      form.appendChild(validationRule);

      const label = document.createElement('label');
      label.setAttribute('for', 'username-required');
      form.appendChild(label);

      const customValidator = new Validator(form, {
        resultClassName: 'custom-result-',
      });

      customValidator.checkValidity(input);
      expect(label.classList.contains('custom-result-0')).toBe(true);

      input.value = 'test';
      customValidator.checkValidity(input);
      expect(label.classList.contains('custom-result-1')).toBe(true);

      customValidator.destroy();
    });

    test('should use custom resultClassName with data-validator-label attribute', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      validationRule.id = 'username-required';
      form.appendChild(validationRule);

      const div = document.createElement('div');
      div.setAttribute('data-validator-label', 'username-required');
      form.appendChild(div);

      const customValidator = new Validator(form, {
        resultClassName: 'custom-result-',
      });

      customValidator.checkValidity(input);
      expect(div.classList.contains('custom-result-0')).toBe(true);

      input.value = 'test';
      customValidator.checkValidity(input);
      expect(div.classList.contains('custom-result-1')).toBe(true);

      customValidator.destroy();
    });

    test('should use custom okClassName and ngClassName', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        okClassName: 'custom-ok',
        ngClassName: 'custom-ng',
      });

      customValidator.checkValidity(input);
      expect(input.classList.contains('custom-ng')).toBe(true);
      expect(input.classList.contains('custom-ok')).toBe(false);

      input.value = 'test';
      customValidator.checkValidity(input);
      expect(input.classList.contains('custom-ok')).toBe(true);
      expect(input.classList.contains('custom-ng')).toBe(false);

      customValidator.destroy();
    });

    test('should call onSubmitFailed callback', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onSubmitFailedMock = vi.fn();
      const customValidator = new Validator(form, {
        onSubmitFailed: onSubmitFailedMock,
      });

      customValidator.checkFormValidity();
      expect(onSubmitFailedMock).toHaveBeenCalled();

      customValidator.destroy();
    });

    test('should call onInvalid callback', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onInvalidMock = vi.fn();
      const customValidator = new Validator(form, {
        onInvalid: onInvalidMock,
      });

      customValidator.checkValidity(input);
      expect(onInvalidMock).toHaveBeenCalled();

      customValidator.destroy();
    });

    test('should use shouldValidate option with onBlur', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        shouldValidate: 'onBlur',
      });

      // Should not validate on input
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(false);

      // Should validate on focusout
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // Should validate on focusout with valid value
      input.value = 'test';
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);

      customValidator.destroy();
    });

    test('should use shouldValidate option with onChange', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        shouldValidate: 'onChange',
      });

      // Should not validate on input
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // Should validate on change
      input.dispatchEvent(new Event('change', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // Should validate on change with valid value
      input.value = 'test';
      input.dispatchEvent(new Event('change', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);

      customValidator.destroy();
    });

    test('should use shouldValidateOnSubmit option and shouldValidate option with false', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        shouldValidate: false,
        shouldValidateOnSubmit: true,
      });

      // Should not validate on input
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(false);

      // Should not validate on focusout
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(false);

      // Should not validate on change
      input.dispatchEvent(new Event('change', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(false);

      // Should validate on submit
      const submitEvent = new Event('submit', { cancelable: true });
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('invalid')).toBe(true);

      // Should validate on submit with valid value
      input.value = 'test';
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('valid')).toBe(true);

      customValidator.destroy();
    });

    test('should use custom validation rules', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = 'test';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#custom';
      form.appendChild(validationRule);

      const customRule = vi.fn().mockReturnValue(true);
      const customValidator = new Validator(form, {
        customRules: {
          custom: customRule,
        },
      });

      customValidator.checkValidity(input);
      expect(customRule).toHaveBeenCalled();

      customValidator.destroy();
    });

    test('should handle shouldFocusOnSubmitFailed option', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        shouldScrollOnSubmitFailed: false,
        shouldFocusOnSubmitFailed: true,
      });

      // Initially, no element should be focused
      expect(document.activeElement).toBe(document.body);

      const submitEvent = new Event('submit', { cancelable: true });
      form.dispatchEvent(submitEvent);

      // After validation fails, the invalid input should be focused
      expect(document.activeElement).toBe(input);

      customValidator.destroy();
    });

    test('should not focus when shouldFocusOnSubmitFailed is false', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const customValidator = new Validator(form, {
        shouldFocusOnSubmitFailed: false,
      });

      // Initially, no element should be focused
      expect(document.activeElement).toBe(document.body);

      const submitEvent = new Event('submit', { cancelable: true });
      form.dispatchEvent(submitEvent);

      // Focus should not change when option is false
      expect(document.activeElement).toBe(document.body);

      customValidator.destroy();
    });

    test('should call onValid callback', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = 'test';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onValidMock = vi.fn();
      const customValidator = new Validator(form, {
        onValid: onValidMock,
      });

      customValidator.checkValidity(input);
      expect(onValidMock).toHaveBeenCalled();

      customValidator.destroy();
    });

    test('should call onValidated callback for valid input', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = 'test';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onValidatedMock = vi.fn();
      const customValidator = new Validator(form, {
        onValidated: onValidatedMock,
      });

      customValidator.checkValidity(input);
      expect(onValidatedMock).toHaveBeenCalledWith(
        expect.arrayContaining([
          expect.objectContaining({
            ok: true,
            element: input,
          }),
        ]),
        input
      );

      customValidator.destroy();
    });

    test('should call onValidated callback for invalid input', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onValidatedMock = vi.fn();
      const customValidator = new Validator(form, {
        onValidated: onValidatedMock,
      });

      customValidator.checkValidity(input);
      expect(onValidatedMock).toHaveBeenCalledWith(
        expect.arrayContaining([
          expect.objectContaining({
            ok: false,
            element: input,
          }),
        ]),
        input
      );

      customValidator.destroy();
    });

    test('should call onValidated callback for form validation', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      const onValidatedMock = vi.fn();
      const customValidator = new Validator(form, {
        onValidated: onValidatedMock,
      });

      customValidator.checkFormValidity();
      expect(onValidatedMock).toHaveBeenCalledWith(
        expect.arrayContaining([
          expect.objectContaining({
            ok: false,
            element: input,
          }),
        ]),
        form
      );

      input.value = 'test';
      customValidator.checkFormValidity();
      expect(onValidatedMock).toHaveBeenCalledWith(
        expect.arrayContaining([
          expect.objectContaining({
            ok: true,
            element: input,
          }),
        ]),
        form
      );

      customValidator.destroy();
    });
  });

  describe('Accessibility', () => {
    test('should set aria-invalid attribute on input elements', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'username:v#required';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // Invalid state
      validator.checkValidity(input);
      expect(input.getAttribute('aria-invalid')).toBe('true');

      // Valid state
      input.value = 'test';
      validator.checkValidity(input);
      expect(input.getAttribute('aria-invalid')).toBe('false');
    });

    test('should set aria-invalid attribute on select elements', () => {
      const select = document.createElement('select');
      select.name = 'country';
      const option = document.createElement('option');
      option.value = '';
      select.appendChild(option);
      form.appendChild(select);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'country:v#required';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // Invalid state
      validator.checkValidity(select);
      expect(select.getAttribute('aria-invalid')).toBe('true');

      // Valid state
      const validOption = document.createElement('option');
      validOption.value = 'japan';
      select.appendChild(validOption);
      select.value = 'japan';
      validator.checkValidity(select);
      expect(select.getAttribute('aria-invalid')).toBe('false');
    });

    test('should set aria-invalid attribute on textarea elements', () => {
      const textarea = document.createElement('textarea');
      textarea.name = 'description';
      textarea.value = '';
      form.appendChild(textarea);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'description:v#required';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // Invalid state
      validator.checkValidity(textarea);
      expect(textarea.getAttribute('aria-invalid')).toBe('true');

      // Valid state
      textarea.value = 'test description';
      validator.checkValidity(textarea);
      expect(textarea.getAttribute('aria-invalid')).toBe('false');
    });

    test('should set aria-invalid attribute on multiple form elements during form validation', () => {
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'username';
      input.value = '';
      form.appendChild(input);

      const textarea = document.createElement('textarea');
      textarea.name = 'description';
      textarea.value = '';
      form.appendChild(textarea);

      const select = document.createElement('select');
      select.name = 'country';
      const defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.text = '選択してください';
      const validOption = document.createElement('option');
      validOption.value = 'japan';
      validOption.text = '日本';
      select.appendChild(defaultOption);
      select.appendChild(validOption);
      form.appendChild(select);

      // Add validation rules for all fields
      ['username', 'description', 'country'].forEach((name) => {
        const rule = document.createElement('input');
        rule.type = 'hidden';
        rule.name = `${name}:v#required`;
        form.appendChild(rule);
      });

      validator = new Validator(form);

      // All fields invalid
      validator.checkFormValidity();
      expect(input.getAttribute('aria-invalid')).toBe('true');
      expect(textarea.getAttribute('aria-invalid')).toBe('true');
      expect(select.getAttribute('aria-invalid')).toBe('true');

      // Make all fields valid
      input.value = 'test';
      textarea.value = 'description';
      select.value = 'japan';
      validator.checkFormValidity();
      expect(input.getAttribute('aria-invalid')).toBe('false');
      expect(textarea.getAttribute('aria-invalid')).toBe('false');
      expect(select.getAttribute('aria-invalid')).toBe('false');
    });

    test('should handle checkbox groups with aria-invalid attribute', () => {
      // Create checkbox group
      const checkboxes = Array.from({ length: 3 }, (_, i) => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'hobbies[]';
        checkbox.value = `hobby${i + 1}`;
        form.appendChild(checkbox);
        return checkbox;
      });

      // Add validation rule for minimum checked items
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'hobbies:v#all_minChecked';
      validationRule.value = '2';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // Invalid state (less than 2 checked)
      checkboxes[0].checked = true;
      validator.checkFormValidity();
      checkboxes.forEach((checkbox) => {
        expect(checkbox.getAttribute('aria-invalid')).toBe('true');
      });

      // Valid state (2 checked)
      checkboxes[1].checked = true;
      validator.checkFormValidity();
      checkboxes.forEach((checkbox) => {
        expect(checkbox.getAttribute('aria-invalid')).toBe('false');
      });
    });
  });

  test('should validate required field', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'username';
    input.value = '';
    form.appendChild(input);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'username:v#required';
    form.appendChild(validationRule);

    validator = new Validator(form);
    expect(validator.checkValidity(input)).toBe(false);

    input.value = 'input';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate email field', () => {
    const input = document.createElement('input');
    input.type = 'email';
    input.name = 'email';
    input.value = 'invalid-email';
    form.appendChild(input);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'email:v#email';
    validationRule.value = 'true';
    form.appendChild(validationRule);

    validator = new Validator(form);
    expect(validator.checkValidity(input)).toBe(false);

    input.value = 'test@example.com';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate url field', () => {
    const input = document.createElement('input');
    input.type = 'url';
    input.name = 'website';
    input.value = 'invalid-url';
    form.appendChild(input);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'website:v#url';
    validationRule.value = 'true';
    form.appendChild(validationRule);

    validator = new Validator(form);
    expect(validator.checkValidity(input)).toBe(false);

    input.value = 'https://example.com';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate minlength field', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'password';
    input.value = '12345';
    form.appendChild(input);

    const validation = document.createElement('input');
    validation.type = 'hidden';
    validation.id = 'password-minlength';
    validation.name = 'password';
    validation.value = '6';
    form.appendChild(validation);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'password:v#minlength';
    validationRule.value = '6';
    form.appendChild(validationRule);

    validator = new Validator(form);
    expect(validator.checkValidity(input)).toBe(false);

    input.value = '123456';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate maxlength field', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'username';
    input.value = '12345678901';
    form.appendChild(input);

    const validation = document.createElement('input');
    validation.type = 'hidden';
    validation.id = 'username-maxlength';
    validation.name = 'username';
    validation.value = '10';
    form.appendChild(validation);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'username:v#maxlength';
    validationRule.value = '10';
    form.appendChild(validationRule);

    validator = new Validator(form);
    expect(validator.checkValidity(input)).toBe(false);

    input.value = '1234567890';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate form submission', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'username';
    input.value = '';
    form.appendChild(input);

    const validation = document.createElement('input');
    validation.type = 'hidden';
    validation.id = 'username-required';
    validation.name = 'username';
    validation.value = 'true';
    form.appendChild(validation);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'username:v#required';
    validationRule.value = 'true';
    form.appendChild(validationRule);

    validator = new Validator(form);
    const submitEvent = new Event('submit', { cancelable: true });
    form.dispatchEvent(submitEvent);
    expect(validator.checkFormValidity()).toBe(false);

    input.value = 'test';
    expect(validator.checkFormValidity()).toBe(true);
  });

  test('should validate required field after disabled is removed', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'username';
    input.value = '';
    form.appendChild(input);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'username:v#required';
    validationRule.disabled = true;
    form.appendChild(validationRule);

    validator = new Validator(form);
    // Initially disabled, so validation should pass
    expect(validator.checkValidity(input)).toBe(true);

    // Remove disabled attribute
    validationRule.disabled = false;
    // Now validation should fail because field is required
    expect(validator.checkValidity(input)).toBe(false);

    // Set valid value
    input.value = 'test';
    expect(validator.checkValidity(input)).toBe(true);

    // Disable validation rule again
    validationRule.disabled = true;
    // Validation should pass even with empty value when disabled
    input.value = '';
    expect(validator.checkValidity(input)).toBe(true);
  });

  test('should validate form submission with disabled validation rules', () => {
    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'username';
    input.value = '';
    form.appendChild(input);

    const validationRule = document.createElement('input');
    validationRule.type = 'hidden';
    validationRule.name = 'username:v#required';
    validationRule.disabled = true;
    form.appendChild(validationRule);

    validator = new Validator(form);
    // Initially disabled, so form should be valid
    const submitEvent = new Event('submit', { cancelable: true });
    form.dispatchEvent(submitEvent);
    expect(validator.checkFormValidity()).toBe(true);

    // Remove disabled attribute
    validationRule.disabled = false;
    // Now form should be invalid because field is required
    form.dispatchEvent(submitEvent);
    expect(validator.checkFormValidity()).toBe(false);

    // Set valid value
    input.value = 'test';
    form.dispatchEvent(submitEvent);
    expect(validator.checkFormValidity()).toBe(true);

    // Disable validation rule again
    validationRule.disabled = true;
    // Form should be valid even with empty value when disabled
    input.value = '';
    form.dispatchEvent(submitEvent);
    expect(validator.checkFormValidity()).toBe(true);
  });

  describe('Dynamic form elements', () => {
    let form: HTMLFormElement;
    let validator: Validator;

    beforeEach(() => {
      form = document.createElement('form');
      document.body.appendChild(form);
    });

    afterEach(() => {
      document.body.removeChild(form);
      if (validator) {
        validator.destroy();
      }
    });

    test('should validate dynamically added input elements on focusout event', () => {
      validator = new Validator(form, {
        shouldValidate: 'onBlur',
      });
      // 動的に追加するinput要素を作成
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'dynamic-input';
      input.value = '';

      // バリデーションルール用のhidden inputを作成
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'dynamic-input:v#required';
      validationRule.id = 'dynamic-input-required';

      // エラーメッセージ用の要素を作成
      const errorMessage = document.createElement('div');
      errorMessage.id = 'dynamic-input-required';
      errorMessage.textContent = 'この項目は必須です';

      // 要素をフォームに追加
      form.appendChild(input);
      form.appendChild(validationRule);
      form.appendChild(errorMessage);

      // focusoutイベントでバリデーションが実行されることを確認
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度focusoutイベントを発火
      input.value = 'test';
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should validate dynamically added input elements on input event', () => {
      validator = new Validator(form, {
        shouldValidate: 'onChange',
      });
      // 動的に追加するinput要素を作成
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'dynamic-input';
      input.value = '';

      // バリデーションルール用のhidden inputを作成
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'dynamic-input:v#required';
      validationRule.id = 'dynamic-input-required';

      // エラーメッセージ用の要素を作成
      const errorMessage = document.createElement('div');
      errorMessage.id = 'dynamic-input-required';
      errorMessage.textContent = 'この項目は必須です';

      // 要素をフォームに追加
      form.appendChild(input);
      form.appendChild(validationRule);
      form.appendChild(errorMessage);

      // inputイベントでバリデーションが実行されることを確認
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度inputイベントを発火
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should validate dynamically added select elements on change event', () => {
      validator = new Validator(form, {
        shouldValidate: 'onChange',
      });
      // 動的に追加するselect要素を作成
      const select = document.createElement('select');
      select.name = 'dynamic-select';

      // オプションを追加
      const defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = '選択してください';
      const validOption = document.createElement('option');
      validOption.value = 'valid';
      validOption.textContent = '有効な選択肢';
      select.appendChild(defaultOption);
      select.appendChild(validOption);

      // バリデーションルール用のhidden inputを作成
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'dynamic-select:v#required';
      validationRule.id = 'dynamic-select-required';

      // エラーメッセージ用の要素を作成
      const errorMessage = document.createElement('div');
      errorMessage.id = 'dynamic-select-required';
      errorMessage.textContent = 'この項目は必須です';

      // 要素をフォームに追加
      form.appendChild(select);
      form.appendChild(validationRule);
      form.appendChild(errorMessage);

      // changeイベントでバリデーションが実行されることを確認
      select.dispatchEvent(new Event('change', { bubbles: true }));
      expect(select.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度changeイベントを発火
      select.value = 'valid';
      select.dispatchEvent(new Event('change', { bubbles: true }));
      expect(select.classList.contains('valid')).toBe(true);
    });

    test('should validate dynamically added elements on form submit', () => {
      validator = new Validator(form, {
        shouldValidate: false,
        shouldValidateOnSubmit: true,
      });
      // 動的に追加するinput要素を作成
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'dynamic-input';
      input.value = '';

      // バリデーションルール用のhidden inputを作成
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'dynamic-input:v#required';
      validationRule.id = 'dynamic-input-required';

      // エラーメッセージ用の要素を作成
      const errorMessage = document.createElement('div');
      errorMessage.id = 'dynamic-input-required';
      errorMessage.textContent = 'この項目は必須です';

      // 要素をフォームに追加
      form.appendChild(input);
      form.appendChild(validationRule);
      form.appendChild(errorMessage);

      // submitイベントでバリデーションが実行されることを確認
      const submitEvent = new Event('submit', { cancelable: true });
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度submitイベントを発火
      input.value = 'test';
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should validate multiple dynamically added elements with different validation timings', () => {
      validator = new Validator(form, {
        shouldValidate: 'onChange',
      });
      // 動的に追加するinput要素を作成
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'dynamic-input';
      input.value = '';

      // 動的に追加するselect要素を作成
      const select = document.createElement('select');
      select.name = 'dynamic-select';
      const defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = '選択してください';
      const validOption = document.createElement('option');
      validOption.value = 'valid';
      validOption.textContent = '有効な選択肢';
      select.appendChild(defaultOption);
      select.appendChild(validOption);

      // バリデーションルール用のhidden inputを作成
      const inputValidationRule = document.createElement('input');
      inputValidationRule.type = 'hidden';
      inputValidationRule.name = 'dynamic-input:v#required';
      inputValidationRule.id = 'dynamic-input-required';

      const selectValidationRule = document.createElement('input');
      selectValidationRule.type = 'hidden';
      selectValidationRule.name = 'dynamic-select:v#required';
      selectValidationRule.id = 'dynamic-select-required';

      // エラーメッセージ用の要素を作成
      const inputError = document.createElement('div');
      inputError.id = 'dynamic-input-required';
      inputError.textContent = 'この項目は必須です';

      const selectError = document.createElement('div');
      selectError.id = 'dynamic-select-required';
      selectError.textContent = 'この項目は必須です';

      // 要素をフォームに追加
      form.appendChild(input);
      form.appendChild(select);
      form.appendChild(inputValidationRule);
      form.appendChild(selectValidationRule);
      form.appendChild(inputError);
      form.appendChild(selectError);

      // inputイベントでinput要素のバリデーションが実行されることを確認
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // changeイベントでselect要素のバリデーションが実行されることを確認
      select.dispatchEvent(new Event('change', { bubbles: true }));
      expect(select.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度イベントを発火
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);

      select.value = 'valid';
      select.dispatchEvent(new Event('change', { bubbles: true }));
      expect(select.classList.contains('valid')).toBe(true);

      // submitイベントで両方の要素が有効であることを確認
      const submitEvent = new Event('submit', { cancelable: true });
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('valid')).toBe(true);
      expect(select.classList.contains('valid')).toBe(true);
    });
  });

  describe('shouldValidateOnSubmit option', () => {
    let form: HTMLFormElement;
    let input: HTMLInputElement;
    let validator: Validator;

    beforeEach(() => {
      form = document.createElement('form');
      input = document.createElement('input');
      input.type = 'text';
      input.name = 'test';
      input.value = '';
      form.appendChild(input);

      // バリデーションルールを追加
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'test:v#required';
      validationRule.id = 'test-required';
      form.appendChild(validationRule);

      document.body.appendChild(form);
    });

    afterEach(() => {
      document.body.removeChild(form);
      if (validator) {
        validator.destroy();
      }
    });

    test('should not validate on submit when shouldValidateOnSubmit is false', () => {
      validator = new Validator(form, {
        shouldValidateOnSubmit: false,
      });

      const submitEvent = new Event('submit', { bubbles: true });
      form.dispatchEvent(submitEvent);

      expect(input.classList.contains('invalid')).toBe(false);
      expect(input.classList.contains('valid')).toBe(false);
    });

    test('should validate on submit when shouldValidateOnSubmit is true', () => {
      validator = new Validator(form, {
        shouldValidateOnSubmit: true,
      });

      const submitEvent = new Event('submit', { bubbles: true });
      form.dispatchEvent(submitEvent);

      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度submitイベントを発火
      input.value = 'test';
      form.dispatchEvent(submitEvent);
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should still validate on blur even when shouldValidateOnSubmit is false', () => {
      validator = new Validator(form, {
        shouldValidateOnSubmit: false,
        shouldValidate: 'onBlur',
      });

      const blurEvent = new Event('focusout', { bubbles: true });
      input.dispatchEvent(blurEvent);

      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度blurイベントを発火
      input.value = 'test';
      input.dispatchEvent(blurEvent);
      expect(input.classList.contains('valid')).toBe(true);
    });
  });

  describe('form attribute validation', () => {
    let form: HTMLFormElement;
    let validator: Validator;

    beforeEach(() => {
      form = document.createElement('form');
      form.id = 'test-form';
      document.body.appendChild(form);
    });

    afterEach(() => {
      document.body.removeChild(form);
      if (validator) {
        validator.destroy();
      }
    });

    test('should validate fields with form attribute', () => {
      // フォームの外に配置されたinput要素を作成
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'external-input';
      input.value = '';
      input.setAttribute('form', 'test-form');
      document.body.appendChild(input);

      // バリデーションルールを追加
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'external-input:v#required';
      validationRule.id = 'external-input-required';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // バリデーションが実行されることを確認
      validator.checkValidity(input);
      expect(input.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度バリデーション
      input.value = 'test';
      validator.checkValidity(input);
      expect(input.classList.contains('valid')).toBe(true);

      document.body.removeChild(input);
    });

    test('should not validate fields without form attribute', () => {
      // フォームの外に配置されたinput要素を作成（form属性なし）
      const input = document.createElement('input');
      input.type = 'text';
      input.name = 'external-input';
      input.value = '';
      document.body.appendChild(input);

      // バリデーションルールを追加
      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'external-input:v#required';
      validationRule.id = 'external-input-required';
      form.appendChild(validationRule);

      validator = new Validator(form);

      // バリデーションが実行されないことを確認
      validator.checkValidity(input);
      expect(input.classList.contains('invalid')).toBe(false);
      expect(input.classList.contains('valid')).toBe(false);

      document.body.removeChild(input);
    });

    test('should validate multiple fields with form attribute on form submit', () => {
      // フォームの外に配置された複数のinput要素を作成
      const inputs = Array.from({ length: 3 }, (_, i) => {
        const input = document.createElement('input');
        input.type = 'text';
        input.name = `external-input-${i}`;
        input.value = '';
        input.setAttribute('form', 'test-form');
        document.body.appendChild(input);

        // バリデーションルールを追加
        const validationRule = document.createElement('input');
        validationRule.type = 'hidden';
        validationRule.name = `external-input-${i}:v#required`;
        validationRule.id = `external-input-${i}-required`;
        form.appendChild(validationRule);

        return input;
      });

      validator = new Validator(form);

      // フォーム送信時のバリデーション
      const submitEvent = new Event('submit', { bubbles: true });
      form.dispatchEvent(submitEvent);

      // すべてのフィールドがバリデーション対象となることを確認
      inputs.forEach((input) => {
        expect(input.classList.contains('invalid')).toBe(true);
      });

      // 有効な値を設定して再度バリデーション
      inputs.forEach((input) => {
        input.value = 'test';
      });
      form.dispatchEvent(submitEvent);

      // すべてのフィールドが有効となることを確認
      inputs.forEach((input) => {
        expect(input.classList.contains('valid')).toBe(true);
      });

      // クリーンアップ
      inputs.forEach((input) => {
        document.body.removeChild(input);
      });
    });

    test('should handle mix of internal and external form fields', () => {
      // フォーム内のinput要素
      const internalInput = document.createElement('input');
      internalInput.type = 'text';
      internalInput.name = 'internal-input';
      internalInput.value = '';
      form.appendChild(internalInput);

      const validationRule = document.createElement('input');
      validationRule.type = 'hidden';
      validationRule.name = 'internal-input:v#required';
      validationRule.id = 'internal-input-required';
      form.appendChild(validationRule);

      // フォームの外に配置されたinput要素
      const externalInput = document.createElement('input');
      externalInput.type = 'text';
      externalInput.name = 'external-input';
      externalInput.value = '';
      externalInput.setAttribute('form', 'test-form');
      document.body.appendChild(externalInput);

      const externalValidationRule = document.createElement('input');
      externalValidationRule.type = 'hidden';
      externalValidationRule.name = 'external-input:v#required';
      externalValidationRule.id = 'external-input-required';
      externalValidationRule.setAttribute('form', 'test-form');
      document.body.appendChild(externalValidationRule);

      validator = new Validator(form);

      // フォーム送信時のバリデーション
      const submitEvent = new Event('submit', { bubbles: true });
      form.dispatchEvent(submitEvent);

      // 両方のフィールドがバリデーション対象となることを確認
      expect(internalInput.classList.contains('invalid')).toBe(true);
      expect(externalInput.classList.contains('invalid')).toBe(true);

      // 有効な値を設定して再度バリデーション
      internalInput.value = 'test';
      externalInput.value = 'test';
      form.dispatchEvent(submitEvent);

      // 両方のフィールドが有効となることを確認
      expect(internalInput.classList.contains('valid')).toBe(true);
      expect(externalInput.classList.contains('valid')).toBe(true);

      document.body.removeChild(externalInput);
    });
  });
  describe('shouldRevalidate option', () => {
    let form: HTMLFormElement;
    let input: HTMLInputElement;
    let validator: Validator;

    beforeEach(() => {
      form = document.createElement('form');
      input = document.createElement('input');
      input.name = 'test';
      form.appendChild(input);
      document.body.appendChild(form);
    });

    afterEach(() => {
      document.body.removeChild(form);
      if (validator) {
        validator.destroy();
      }
    });

    test('should revalidate failed fields on change when shouldRevalidate is onChange', () => {
      // バリデーションルールを追加
      const validationInput = document.createElement('input');
      validationInput.name = 'test:validator#required';
      validationInput.value = 'true';
      form.appendChild(validationInput);

      // バリデーターを初期化
      validator = new Validator(form, {
        shouldValidate: false,
        shouldRevalidate: 'onChange',
      });

      // フォーム送信をシミュレートしてバリデーションを失敗させる
      form.dispatchEvent(new Event('submit'));
      expect(input.classList.contains('invalid')).toBe(true);

      // 値を変更して再バリデーションをトリガー
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should revalidate failed fields on blur when shouldRevalidate is onBlur', () => {
      // バリデーションルールを追加
      const validationInput = document.createElement('input');
      validationInput.name = 'test:validator#required';
      validationInput.value = 'true';
      form.appendChild(validationInput);

      // バリデーターを初期化
      validator = new Validator(form, {
        shouldValidate: false,
        shouldRevalidate: 'onBlur',
      });

      // フォーム送信をシミュレートしてバリデーションを失敗させる
      form.dispatchEvent(new Event('submit'));
      expect(input.classList.contains('invalid')).toBe(true);

      // 値を変更しても再バリデーションされないことを確認
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // フォーカスを外して再バリデーションをトリガー
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should not revalidate failed fields until submit when shouldRevalidate is false', () => {
      // バリデーションルールを追加
      const validationInput = document.createElement('input');
      validationInput.name = 'test:validator#required';
      validationInput.value = 'true';
      form.appendChild(validationInput);

      // バリデーターを初期化
      validator = new Validator(form, {
        shouldValidate: false,
        shouldRevalidate: false,
      });

      // フォーム送信をシミュレートしてバリデーションを失敗させる
      form.dispatchEvent(new Event('submit'));
      expect(input.classList.contains('invalid')).toBe(true);

      // 値を変更しても再バリデーションされないことを確認
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // フォーカスを外しても再バリデーションされないことを確認
      input.dispatchEvent(new Event('focusout', { bubbles: true }));
      expect(input.classList.contains('invalid')).toBe(true);

      // フォーム送信で再バリデーションされることを確認
      form.dispatchEvent(new Event('submit'));
      expect(input.classList.contains('valid')).toBe(true);
    });

    test('should handle multiple failed fields with different revalidation settings', () => {
      // 2つの入力フィールドを作成
      const input2 = document.createElement('input');
      input2.name = 'test2';
      form.appendChild(input2);

      // バリデーションルールを追加
      const validationInput1 = document.createElement('input');
      validationInput1.name = 'test:validator#required';
      validationInput1.value = 'true';
      form.appendChild(validationInput1);

      const validationInput2 = document.createElement('input');
      validationInput2.name = 'test2:validator#required';
      validationInput2.value = 'true';
      form.appendChild(validationInput2);

      // バリデーターを初期化
      validator = new Validator(form, {
        shouldValidate: false,
        shouldRevalidate: 'onChange',
      });

      // フォーム送信をシミュレートしてバリデーションを失敗させる
      form.dispatchEvent(new Event('submit'));
      expect(input.classList.contains('invalid')).toBe(true);
      expect(input2.classList.contains('invalid')).toBe(true);

      // 最初のフィールドの値を変更
      input.value = 'test';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
      expect(input2.classList.contains('invalid')).toBe(true);

      // 2番目のフィールドの値を変更
      input2.value = 'test2';
      input2.dispatchEvent(new Event('input', { bubbles: true }));
      expect(input.classList.contains('valid')).toBe(true);
      expect(input2.classList.contains('valid')).toBe(true);
    });
  });
});
