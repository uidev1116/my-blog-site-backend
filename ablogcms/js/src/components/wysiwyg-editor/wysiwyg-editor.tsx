import { useEffect, useRef, useCallback, useImperativeHandle, forwardRef } from 'react';

// Import existing wysiwyg function
import useEffectOnce from '@hooks/use-effect-once';
import initWysiwyg from '../../lib/wysiwyg';

export interface WysiwygEditorProps {
  value?: string;
  onChange?: (value: string) => void;
  onBlur?: (value: string) => void;
  onFocus?: () => void;
  options?: Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any
  disabled?: boolean;
  placeholder?: string;
  className?: string;
  id?: string;
  name?: string;
  required?: boolean;
  autoFocus?: boolean;
  readOnly?: boolean;
}

export interface WysiwygEditorRef {
  getInstance: () => any; // eslint-disable-line @typescript-eslint/no-explicit-any
  getHtml: () => string;
  setHtml: (html: string) => void;
  empty: () => void;
  destroy: () => void;
  disable: () => void;
  enable: () => void;
}

const WysiwygEditor = forwardRef<WysiwygEditorRef, WysiwygEditorProps>(
  (
    {
      value = '',
      onChange,
      onBlur,
      onFocus,
      options = {},
      disabled = false,
      placeholder,
      className = '',
      id,
      name,
      required = false,
      autoFocus = false,
      readOnly = false,
    },
    ref
  ) => {
    const containerRef = useRef<HTMLDivElement>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);
    const isInitializedRef = useRef(false);

    // Create textarea element via DOM manipulation
    const createTextarea = useCallback(() => {
      if (!containerRef.current) {
        return;
      }

      // Remove existing textarea if any
      const existingTextarea = containerRef.current.querySelector('textarea');
      if (existingTextarea) {
        existingTextarea.remove();
      }

      // Create new textarea element
      const textarea = document.createElement('textarea');
      textarea.className = className;

      // Set attributes
      if (id) textarea.id = id;
      if (name) textarea.name = name;
      if (placeholder) textarea.placeholder = placeholder;
      if (required) textarea.required = required;
      if (autoFocus) textarea.autofocus = autoFocus;
      if (readOnly) textarea.readOnly = readOnly;

      // Set initial value
      if (value) {
        textarea.value = value;
      }

      // Append to container
      containerRef.current.appendChild(textarea);
      textareaRef.current = textarea;

      return textarea;
    }, [id, name, placeholder, required, autoFocus, readOnly, value, className]);

    // Initialize Trumbowyg using existing function
    const initializeTrumbowyg = useCallback(() => {
      if (!containerRef.current || isInitializedRef.current) return;

      const textarea = createTextarea();
      if (!textarea) return;

      // Use existing wysiwyg function
      initWysiwyg(textarea, options);

      // Set up event listeners after initialization
      const $textarea = $(textarea);

      $textarea.on('tbwchange', () => {
        // @ts-expect-error trumbowygの型定義がない
        const html = $textarea.trumbowyg('html');
        onChange?.(typeof html === 'string' ? html : '');
      });

      $textarea.on('tbwblur', () => {
        // @ts-expect-error trumbowygの型定義がない
        const html = $textarea.trumbowyg('html');
        onBlur?.(typeof html === 'string' ? html : '');
      });

      $textarea.on('tbwfocus', () => {
        onFocus?.();
      });

      // Set initial value after initialization
      if (value) {
        // @ts-expect-error trumbowygの型定義がない
        $textarea.trumbowyg('html', value);
      }

      // Apply disabled state
      if (disabled) {
        // @ts-expect-error trumbowygの型定義がない
        $textarea.trumbowyg('disable');
      }

      isInitializedRef.current = true;
    }, [value, onChange, onBlur, onFocus, disabled, options, createTextarea]);

    // Destroy Trumbowyg
    const destroyTrumbowyg = useCallback(() => {
      if (textareaRef.current && isInitializedRef.current) {
        // @ts-expect-error trumbowygの型定義がない
        $(textareaRef.current).trumbowyg('destroy');
        textareaRef.current = null;
        isInitializedRef.current = false;
      }
    }, []);

    // Initialize on mount
    useEffectOnce(() => {
      initializeTrumbowyg();
      return () => {
        destroyTrumbowyg();
      };
    });

    // Handle value changes
    useEffect(() => {
      if (isInitializedRef.current && textareaRef.current) {
        const $textarea = $(textareaRef.current);
        // @ts-expect-error trumbowygの型定義がない
        const currentHtml = $textarea.trumbowyg('html');
        const currentHtmlString = typeof currentHtml === 'string' ? currentHtml : '';
        if (currentHtmlString !== value) {
          // @ts-expect-error trumbowygの型定義がない
          $textarea.trumbowyg('html', value);
        }
      }
    }, [value]);

    // Handle disabled state changes
    useEffect(() => {
      if (isInitializedRef.current && textareaRef.current) {
        const $textarea = $(textareaRef.current);
        if (disabled) {
          // @ts-expect-error trumbowygの型定義がない
          $textarea.trumbowyg('disable');
        } else {
          // @ts-expect-error trumbowygの型定義がない
          $textarea.trumbowyg('enable');
        }
      }
    }, [disabled]);

    // Expose methods via ref
    useImperativeHandle(
      ref,
      () => ({
        getInstance: () => {
          if (textareaRef.current && isInitializedRef.current) {
            return $(textareaRef.current) as any; // eslint-disable-line @typescript-eslint/no-explicit-any
          }
          return null;
        },
        getHtml: () => {
          if (textareaRef.current && isInitializedRef.current) {
            // @ts-expect-error trumbowygの型定義がない
            const html = $(textareaRef.current).trumbowyg('html');
            return typeof html === 'string' ? html : '';
          }
          return '';
        },
        setHtml: (html: string) => {
          if (textareaRef.current && isInitializedRef.current) {
            // @ts-expect-error trumbowygの型定義がない
            $(textareaRef.current).trumbowyg('html', html);
          }
        },
        empty: () => {
          if (textareaRef.current && isInitializedRef.current) {
            // @ts-expect-error trumbowygの型定義がない
            $(textareaRef.current).trumbowyg('empty');
          }
        },
        destroy: destroyTrumbowyg,
        disable: () => {
          if (textareaRef.current && isInitializedRef.current) {
            // @ts-expect-error trumbowygの型定義がない
            $(textareaRef.current).trumbowyg('disable');
          }
        },
        enable: () => {
          if (textareaRef.current && isInitializedRef.current) {
            // @ts-expect-error trumbowygの型定義がない
            $(textareaRef.current).trumbowyg('enable');
          }
        },
      }),
      [destroyTrumbowyg]
    );

    return <div ref={containerRef} />;
  }
);

WysiwygEditor.displayName = 'WysiwygEditor';

export default WysiwygEditor;
