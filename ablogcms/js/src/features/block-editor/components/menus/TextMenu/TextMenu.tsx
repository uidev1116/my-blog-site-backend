import { BubbleMenu, Editor } from '@tiptap/react';
import { memo, useEffect, useState, useRef, useMemo } from 'react';
import { Popover, PopoverTrigger, PopoverContent } from '@components/popover';
import { useSettingsContext } from '@features/block-editor/context/EditorSettings';
import { ColorPicker, AlignPicker } from '@features/block-editor/components/panels';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { Toolbar } from '@features/block-editor/components/ui/Toolbar';
import { useBlockMenus } from '@features/block-editor/hooks/useBlockMenus';
import { useTextmenuCommands } from './hooks/useTextmenuCommands';
import { useTextmenuStates } from './hooks/useTextmenuStates';
import { ContentTypePicker } from './components/ContentTypePicker';
import { FontFamilyPicker } from './components/FontFamilyPicker';
import { FontSizePicker } from './components/FontSizePicker';
import { CustomClassPicker } from './components/CustomClassPicker';
import { EditLinkPopover } from './components/EditLinkPopover';

const MemoButton = memo(Toolbar.Button);
const MemoContentTypePicker = memo(ContentTypePicker);
const MemoColorPicker = memo(ColorPicker);
const MemoFontFamilyPicker = memo(FontFamilyPicker);
const MemoFontSizePicker = memo(FontSizePicker);
const MemoCustomClassPicker = memo(CustomClassPicker);

export type TextMenuProps = {
  editor: Editor;
  appendTo?: React.RefObject<any>; // eslint-disable-line @typescript-eslint/no-explicit-any
};

export const TextMenu = ({ editor, appendTo }: TextMenuProps) => {
  const commands = useTextmenuCommands(editor);
  const states = useTextmenuStates(editor);
  const { features, blockMenus } = useSettingsContext();
  const { getFilteredBlockMenus } = useBlockMenus({ blockMenus });
  const menuRef = useRef<HTMLDivElement>(null);
  const [showLeft, setShowLeft] = useState(false);
  const [showRight, setShowRight] = useState(false);

  const updateScrollButtons = () => {
    const el = menuRef.current;
    if (!el) return;

    setShowLeft(el.scrollLeft > 0);
    setShowRight(el.scrollLeft + el.clientWidth < el.scrollWidth - 1);
  };

  const scrollLeft = () => {
    menuRef.current?.scrollBy({ left: -150, behavior: 'smooth' });
  };

  const scrollRight = () => {
    menuRef.current?.scrollBy({ left: 150, behavior: 'smooth' });
  };

  const align = useMemo(() => {
    if (states.isAlignLeft) {
      return 'left';
    }
    if (states.isAlignCenter) {
      return 'center';
    }
    if (states.isAlignRight) {
      return 'right';
    }
    return 'left';
  }, [states.isAlignLeft, states.isAlignCenter, states.isAlignRight]);

  const handleAlign = (newAlign: 'left' | 'center' | 'right') => {
    if (newAlign === 'left') {
      commands.onAlignLeft();
    } else if (newAlign === 'center') {
      commands.onAlignCenter();
    } else if (newAlign === 'right') {
      commands.onAlignRight();
    }
  };

  useEffect(() => {
    const el = menuRef.current;
    if (!el) return;

    el.addEventListener('scroll', updateScrollButtons);
    document.addEventListener('resize', updateScrollButtons);
    return () => {
      el.removeEventListener('scroll', updateScrollButtons);
      document.removeEventListener('resize', updateScrollButtons);
    };
  }, [editor]);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Tab') {
        // フォーカストラップ
        const toolbar = menuRef.current;
        if (toolbar) {
          // ツールバーが存在する場合、ツールバーにフォーカスを移動
          toolbar.focus();
        }
      }
    };

    // useToolbar のkeydown よりも先に実行するために capture を true にしている
    editor.view.dom.addEventListener('keydown', handleKeyDown, { capture: true });
    return () => {
      editor.view.dom.removeEventListener('keydown', handleKeyDown, { capture: true });
    };
  }, [editor.view.dom]);

  const handleToolbarKeyDown = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (event.key === 'Tab') {
      editor.commands.focus(); // エディタにフォーカスを戻す
    }
  };

  return (
    <BubbleMenu
      tippyOptions={{
        theme: 'text-menu',
        maxWidth: '100%',
        appendTo: () => appendTo?.current ?? document.body,
        onShow() {
          // メニュー表示時にスクロールボタンの表示を更新
          requestAnimationFrame(() => {
            updateScrollButtons();
          });
        },
        popperOptions: {
          placement: 'top-start',
          modifiers: [
            {
              name: 'preventOverflow',
              options: {
                boundary: appendTo?.current ?? 'clippingParents',
                padding: 8,
              },
            },
            {
              name: 'flip',
              options: {
                fallbackPlacements: ['bottom-start', 'top'],
              },
            },
          ],
        },
        zIndex: 100018,
      }}
      editor={editor}
      pluginKey="textMenu"
      shouldShow={states.shouldShow}
      updateDelay={100}
    >
      <Toolbar
        ref={menuRef}
        className="acms-admin-block-editor-toolbar-scrollable"
        // useToolbar のkeydown よりも先に実行するために Capture で実行
        onKeyDownCapture={handleToolbarKeyDown}
      >
        <MemoContentTypePicker
          menus={getFilteredBlockMenus({ query: '', editor })}
          editor={editor}
          currentPos={editor.state.selection.$from.before(1)}
        />
        <MemoButton
          type="button"
          tooltip="太字"
          tooltipShortcut={['Mod', 'B']}
          onClick={commands.onBold}
          active={states.isBold}
          aria-label="太字"
        >
          <Icon name="format_bold" />
        </MemoButton>
        {features?.textItalic && (
          <MemoButton
            type="button"
            tooltip="斜体"
            tooltipShortcut={['Mod', 'I']}
            onClick={commands.onItalic}
            active={states.isItalic}
            aria-label="斜体"
          >
            <Icon name="format_italic" />
          </MemoButton>
        )}
        {features?.textUnderline && (
          <MemoButton
            type="button"
            tooltip="下線"
            tooltipShortcut={['Mod', 'U']}
            onClick={commands.onUnderline}
            active={states.isUnderline}
            aria-label="下線"
          >
            <Icon name="format_underlined" />
          </MemoButton>
        )}
        {features?.textStrike && (
          <MemoButton
            type="button"
            tooltip="打ち消し線"
            tooltipShortcut={['Mod', 'Shift', 'S']}
            onClick={commands.onStrike}
            active={states.isStrike}
            aria-label="打ち消し線"
          >
            <Icon name="format_strikethrough" />
          </MemoButton>
        )}
        {features?.textCode && (
          <MemoButton
            type="button"
            tooltip="コード"
            tooltipShortcut={['Mod', 'E']}
            onClick={commands.onCode}
            active={states.isCode}
            aria-label="コード"
          >
            <Icon name="code" />
          </MemoButton>
        )}
        <EditLinkPopover
          editor={editor}
          onSetLink={commands.onLink}
          onClearLink={commands.onUnsetLink}
          isActive={states.isLink}
        />
        <AlignPicker align={align} onChange={handleAlign} />
        {features?.textMarker && (
          <Popover modal placement="top-start">
            <PopoverTrigger asChild>
              <MemoButton type="button" tooltip="マーカー" active={!!states.currentHighlight} aria-label="マーカー">
                <Icon name="format_ink_highlighter" />
              </MemoButton>
            </PopoverTrigger>
            <PopoverContent data-elevation="3">
              <MemoColorPicker
                color={states.currentHighlight}
                onChange={commands.onChangeHighlight}
                onClear={commands.onClearHighlight}
              />
            </PopoverContent>
          </Popover>
        )}
        {features?.textColor && (
          <Popover modal placement="top-start">
            <PopoverTrigger asChild>
              <MemoButton type="button" active={!!states.currentColor} tooltip="文字色" aria-label="文字色">
                <Icon name="format_color_text" />
              </MemoButton>
            </PopoverTrigger>
            <PopoverContent data-elevation="3">
              <MemoColorPicker
                color={states.currentColor}
                onChange={commands.onChangeColor}
                onClear={commands.onClearColor}
              />
            </PopoverContent>
          </Popover>
        )}
        {features?.textSubscript && (
          <MemoButton
            type="button"
            tooltip="下付き文字"
            tooltipShortcut={['Mod', ',']}
            onClick={commands.onSubscript}
            active={states.isSubscript}
            aria-label="下付き文字"
          >
            <Icon name="subscript" />
          </MemoButton>
        )}
        {features?.textSuperscript && (
          <MemoButton
            type="button"
            tooltip="上付き文字"
            tooltipShortcut={['Mod', '.']}
            onClick={commands.onSuperscript}
            active={states.isSuperscript}
            aria-label="上付き文字"
          >
            <Icon name="superscript" />
          </MemoButton>
        )}
        {features?.fontSize && (
          <>
            <Toolbar.Divider />
            <MemoFontSizePicker onChange={commands.onSetFontSize} value={states.currentSize || ''} />
          </>
        )}
        {features?.fontFamily && (
          <>
            <Toolbar.Divider />
            <MemoFontFamilyPicker onChange={commands.onSetFont} value={states.currentFont || ''} />
          </>
        )}
        {features?.customClass && (
          <>
            <Toolbar.Divider />
            <MemoCustomClassPicker
              onChange={commands.onCustomClass}
              onRemove={commands.removeCustomClass}
              value={states.currentCustomClass || ''}
            />
          </>
        )}
        {showLeft && (
          <button
            type="button"
            onClick={scrollLeft}
            aria-label="左にスクロール"
            className="acms-admin-block-editor-toolbar-arrow acms-admin-block-editor-toolbar-arrow-left"
          >
            <Icon name="chevron_left" />
          </button>
        )}
        {showRight && (
          <button
            type="button"
            onClick={scrollRight}
            aria-label="右にスクロール"
            className="acms-admin-block-editor-toolbar-arrow acms-admin-block-editor-toolbar-arrow-right"
          >
            <Icon name="chevron_right" />
          </button>
        )}
      </Toolbar>
    </BubbleMenu>
  );
};
