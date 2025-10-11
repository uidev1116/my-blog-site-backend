import {
  ChangeEvent,
  forwardRef,
  TextareaHTMLAttributes,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';

export interface AutoResizeTextareaRef {
  focus: () => void;
  blur: () => void;
  getHeight: () => number;
  recalculateHeight: () => void;
  value: string;
  setValue: (value: string) => void;
}

interface AutoResizeTextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  value?: string;
  onChange?: (e: ChangeEvent<HTMLTextAreaElement>) => void;
  className?: string;
  defaultRows?: number;
  maxRows?: number;
}

/**
 * 行の高さを計算する関数
 * @param node 計算対象の要素
 * @returns 計算された行の高さ (px)
 */
function calculateLineHeight(node: HTMLElement): number {
  // CSSのline-heightを取得
  const style = window.getComputedStyle(node);
  let lineHeightStr = style.lineHeight;
  let lineHeight = parseFloat(lineHeightStr);

  // 単位のない数値の場合はemに変換して処理
  if (lineHeightStr === `${lineHeight}`) {
    const oldLineHeight = node.style.lineHeight;
    node.style.lineHeight = `${lineHeightStr}em`;

    lineHeightStr = window.getComputedStyle(node).lineHeight;
    lineHeight = parseFloat(lineHeightStr);

    // スタイルを元に戻す
    if (oldLineHeight) {
      node.style.lineHeight = oldLineHeight;
    } else {
      node.style.lineHeight = '';
    }
  }

  // 単位変換（pt, mm, cm, inなど）
  if (lineHeightStr.indexOf('pt') !== -1) {
    lineHeight *= 4 / 3;
  } else if (lineHeightStr.indexOf('mm') !== -1) {
    lineHeight *= 96 / 25.4;
  } else if (lineHeightStr.indexOf('cm') !== -1) {
    lineHeight *= 96 / 2.54;
  } else if (lineHeightStr.indexOf('in') !== -1) {
    lineHeight *= 96;
  } else if (lineHeightStr.indexOf('pc') !== -1) {
    lineHeight *= 16;
  }

  lineHeight = Math.round(lineHeight);

  // 'normal'の場合はフォントサイズから計算
  if (lineHeightStr === 'normal') {
    const fontSizeStr = style.fontSize;
    const fontSize = parseFloat(fontSizeStr);
    // 一般的には1.2倍が「normal」の標準
    lineHeight = Math.round(fontSize * 1.2);
  }

  return lineHeight;
}

/**
 * 入力内容に応じて高さが自動調整されるテキストエリアコンポーネント
 *
 * @component
 * @example
 * ```tsx
 * import { useRef } from 'react';
 * import AutoResizeTextarea, { AutoResizeTextareaRef } from './AutoResizeTextarea';
 *
 * function Example() {
 *   const textareaRef = useRef<AutoResizeTextareaRef>(null);
 *
 *   return (
 *     <AutoResizeTextarea
 *       ref={textareaRef}
 *       value="初期値"
 *       onChange={(e) => console.log(e.target.value)}
 *       defaultRows={3}    // 初期行数: 3行
 *       maxRows={10}       // 最大行数: 10行
 *       placeholder="入力してください"
 *     />
 *   );
 * }
 * ```
 *
 * @param props - コンポーネントのプロパティ
 * @param {string} [props.value] - テキストエリアの値
 * @param {function} [props.onChange] - 値変更時のコールバック関数
 * @param {string} [props.className] - 追加のクラス名
 * @param {number} [props.defaultRows=2] - 初期表示時の行数
 * @param {number} [props.maxRows=6] - 最大表示行数
 *
 * @returns 自動リサイズ対応のテキストエリアコンポーネント
 */

const AutoResizeTextarea = forwardRef<AutoResizeTextareaRef, AutoResizeTextareaProps>(
  (
    { value: externalValue, onChange: externalOnChange, className = '', defaultRows = 2, maxRows = 6, ...props },
    ref
  ) => {
    const [internalValue, setInternalValue] = useState(externalValue || '');
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const [lineHeight, setLineHeight] = useState<number>(24);

    const value = externalValue !== undefined ? externalValue : internalValue;

    useEffect(() => {
      const textarea = textareaRef.current;
      if (textarea) {
        const calculatedLineHeight = calculateLineHeight(textarea);
        setLineHeight(calculatedLineHeight);
      }
    }, []);

    const calculateHeight = useCallback(() => {
      const textarea = textareaRef.current;
      if (!textarea) return;

      // 一時的に高さをautoにして実際のコンテンツの高さを取得
      textarea.style.height = 'auto';

      const style = window.getComputedStyle(textarea);
      const borderTop = parseFloat(style.borderTopWidth) || 0;
      const borderBottom = parseFloat(style.borderBottomWidth) || 0;
      const borderHeight = borderTop + borderBottom;
      const paddingTop = parseFloat(style.paddingTop) || 0;
      const paddingBottom = parseFloat(style.paddingBottom) || 0;
      const paddingHeight = paddingTop + paddingBottom;

      // scrollHeightを取得（実際のコンテンツの高さ）
      const { scrollHeight } = textarea;

      const minHeight = defaultRows * lineHeight + borderHeight + paddingHeight;
      const maxHeight = maxRows * lineHeight + borderHeight + paddingHeight;
      const contentHeight = scrollHeight + borderHeight; // scrollHeightはpaddingを含んでいるため、borderHeightのみを加算

      // スクロールの高さを基に、制限内で新しい高さを設定
      const newContentHeight = Math.min(Math.max(contentHeight, minHeight), maxHeight);

      textarea.style.height = `${newContentHeight}px`;
    }, [lineHeight, defaultRows, maxRows]);

    useImperativeHandle(ref, () => ({
      focus: () => textareaRef.current?.focus(),
      blur: () => textareaRef.current?.blur(),
      getHeight: () => textareaRef.current?.offsetHeight || 0,
      recalculateHeight: calculateHeight,
      value,
      setValue: (newValue: string) => {
        if (externalValue === undefined) {
          setInternalValue(newValue);
        }
        if (textareaRef.current) {
          textareaRef.current.value = newValue;
        }
      },
    }));

    useEffect(() => {
      calculateHeight();
    }, [value, lineHeight, defaultRows, maxRows, calculateHeight]);

    const handleChange = (e: ChangeEvent<HTMLTextAreaElement>) => {
      const newValue = e.target.value;
      if (externalValue === undefined) {
        setInternalValue(newValue);
      }
      if (externalOnChange) {
        externalOnChange(e);
      }
    };

    return (
      <textarea
        ref={textareaRef}
        defaultValue={value}
        onChange={handleChange}
        className={className}
        style={{
          resize: 'none',
          ...props.style,
        }}
        rows={defaultRows}
        {...props}
      />
    );
  }
);
AutoResizeTextarea.displayName = 'AutoResizeTextarea';

export default AutoResizeTextarea;
