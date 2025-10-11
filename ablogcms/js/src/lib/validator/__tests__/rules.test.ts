import { describe, test, expect } from 'vitest';
import * as rules from '../rules';
import type { Validation } from '../types';

describe('Validation Rules', () => {
  const dummyInput = document.createElement('input');
  const dummyValidation: Validation = {
    field: 'test',
    rule: 'required',
    arg: '',
    id: 'test',
    name: 'test',
  };

  describe('required', () => {
    test('should validate required fields', () => {
      expect(rules.required('', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.required('test', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('email', () => {
    test('should validate email addresses', () => {
      expect(rules.email('invalid-email', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.email('test@example.com', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.email('test.name@example.co.jp', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.email('test+label@example.com', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('url', () => {
    test('should validate URLs', () => {
      expect(rules.url('invalid-url', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.url('http://example.com', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.url('https://example.com', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.url('https://sub.example.com/path?query=1', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('minlength', () => {
    test('should validate minimum length', () => {
      expect(rules.minlength('1234', '5', dummyInput, dummyValidation)).toBe(false);
      expect(rules.minlength('12345', '5', dummyInput, dummyValidation)).toBe(true);
      expect(rules.minlength('123456', '5', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('maxlength', () => {
    test('should validate maximum length', () => {
      expect(rules.maxlength('123456', '5', dummyInput, dummyValidation)).toBe(false);
      expect(rules.maxlength('12345', '5', dummyInput, dummyValidation)).toBe(true);
      expect(rules.maxlength('1234', '5', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('min', () => {
    test('should validate minimum value', () => {
      expect(rules.min('4', '5', dummyInput, dummyValidation)).toBe(false);
      expect(rules.min('5', '5', dummyInput, dummyValidation)).toBe(true);
      expect(rules.min('6', '5', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('max', () => {
    test('should validate maximum value', () => {
      expect(rules.max('6', '5', dummyInput, dummyValidation)).toBe(false);
      expect(rules.max('5', '5', dummyInput, dummyValidation)).toBe(true);
      expect(rules.max('4', '5', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('regex', () => {
    test('should validate against regular expressions', () => {
      expect(rules.regex('abc', '^[0-9]+$', dummyInput, dummyValidation)).toBe(false);
      expect(rules.regex('123', '^[0-9]+$', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('digits', () => {
    test('should validate digits', () => {
      expect(rules.digits('abc', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.digits('123', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.digits('12.34', '', dummyInput, dummyValidation)).toBe(false);
    });
  });

  describe('hiragana', () => {
    test('should validate hiragana characters', () => {
      expect(rules.hiragana('abc', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.hiragana('アイウ', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.hiragana('あいう', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('katakana', () => {
    test('should validate katakana characters', () => {
      expect(rules.katakana('abc', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.katakana('あいう', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.katakana('アイウ', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('equalTo', () => {
    test('should validate equality', () => {
      const form = document.createElement('form');
      form.id = 'test-form';
      document.body.appendChild(form);

      const input1 = document.createElement('input');
      input1.value = 'test';
      input1.name = 'password';
      form.appendChild(input1);

      const input2 = document.createElement('input');
      input2.value = 'different';
      input2.name = 'confirm';
      form.appendChild(input2);

      const validation: Validation = {
        field: input1.name,
        rule: 'equalTo',
        arg: '',
        id: 'test',
        name: 'test',
      };

      expect(rules.equalTo(input1.value, input2.name, input1, validation)).toBe(false);

      input2.value = 'test';
      expect(rules.equalTo(input1.value, input2.name, input1, validation)).toBe(true);

      document.body.removeChild(form);
    });
  });

  describe('filesize', () => {
    test('should validate file size', () => {
      const smallFile = new File(['small content'], 'small.txt', { type: 'text/plain' });
      const largeFile = new File(['large'.repeat(1000000)], 'large.txt', { type: 'text/plain' });

      const input1 = document.createElement('input');
      input1.type = 'file';
      Object.defineProperty(input1, 'files', {
        value: [smallFile],
        configurable: true,
      });
      expect(rules.filesize('', '1MB', input1, dummyValidation)).toBe(true);

      const input2 = document.createElement('input');
      input2.type = 'file';
      Object.defineProperty(input2, 'files', {
        value: [largeFile],
        configurable: true,
      });
      expect(rules.filesize('', '1KB', input2, dummyValidation)).toBe(false);
    });
  });

  describe('dates', () => {
    test('should validate dates', () => {
      expect(rules.dates('invalid-date', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.dates('2024-03-16', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.dates('2024/03/16', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('times', () => {
    test('should validate times', () => {
      // Invalid times
      expect(rules.times('25:00', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.times('24:00', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.times('23:60', '', dummyInput, dummyValidation)).toBe(false);
      expect(rules.times('23:59:60', '', dummyInput, dummyValidation)).toBe(false);

      // Valid times in HH:MM format
      expect(rules.times('12:00', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.times('23:59', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.times('00:00', '', dummyInput, dummyValidation)).toBe(true);

      // Valid times in HH:MM:SS format
      expect(rules.times('12:00:00', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.times('23:59:59', '', dummyInput, dummyValidation)).toBe(true);
      expect(rules.times('00:00:00', '', dummyInput, dummyValidation)).toBe(true);
    });
  });

  describe('all_MinChecked', () => {
    test('should validate minimum checked items', () => {
      const checkboxes = Array.from({ length: 3 }, () => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        return checkbox;
      });

      checkboxes[0].checked = true;
      expect(rules.all_minChecked('', '2', checkboxes, dummyValidation)).toBe(false);

      checkboxes[1].checked = true;
      expect(rules.all_minChecked('', '2', checkboxes, dummyValidation)).toBe(true);
    });
  });

  describe('all_MaxChecked', () => {
    test('should validate maximum checked items', () => {
      const checkboxes = Array.from({ length: 3 }, () => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = true;
        return checkbox;
      });

      expect(rules.all_maxChecked('', '2', checkboxes, dummyValidation)).toBe(false);

      checkboxes[2].checked = false;
      expect(rules.all_maxChecked('', '2', checkboxes, dummyValidation)).toBe(true);
    });
  });

  describe('all_JustChecked', () => {
    test('should validate exact number of checked items', () => {
      const checkboxes = Array.from({ length: 3 }, () => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        return checkbox;
      });

      checkboxes[0].checked = true;
      expect(rules.all_justChecked('', '2', checkboxes, dummyValidation)).toBe(false);

      checkboxes[1].checked = true;
      expect(rules.all_justChecked('', '2', checkboxes, dummyValidation)).toBe(true);

      checkboxes[2].checked = true;
      expect(rules.all_justChecked('', '2', checkboxes, dummyValidation)).toBe(false);
    });
  });
});
