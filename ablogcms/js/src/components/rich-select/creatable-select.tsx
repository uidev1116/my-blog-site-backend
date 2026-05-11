import { forwardRef, useCallback, useRef, useState } from 'react';
import Select from 'react-select/creatable';
import classnames from 'classnames';
import type { SelectInstance, GroupBase, StylesConfig, InputActionMeta } from 'react-select';
import type { CreatableProps } from 'react-select/creatable';
import useMergeRefs from '@hooks/use-merge-refs';
import DropdownIndicator from './components/dropdown-indicator';
import stylesConfig from './styles';

// 改行（\n）はデフォルトの入力欄が input[type="text"] のため onInputChange では半角空白として扱われるため、デリミターに含めない
const DEFAULT_DELIMITERS = [',', '/'];

const EMPTY_OPTIONS: never[] = [];

/**
 * CreatableSelect 専用: テキストをデリミターで分割し、トリム・空文字除去した配列を返す
 */
function splitByDelimiters(text: string, delimiters: string[]): string[] {
  if (delimiters.length === 0) {
    return [];
  }

  const escapedDelimiters = delimiters.map((d) => (d === '\n' ? '\\n' : d.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  const regex = new RegExp(`${escapedDelimiters.join('|')}+`);
  return text
    .split(regex)
    .map((s) => s.trim())
    .filter(Boolean);
}

interface CreatableSelectProps<
  Option = unknown,
  IsMulti extends boolean = false,
  Group extends GroupBase<Option> = GroupBase<Option>,
> extends CreatableProps<Option, IsMulti, Group> {
  /** 入力時（CreatableSelect 専用）に複数オプションに分割するデリミター。空配列で無効。デフォルト: [',', '\\n', '/'] */
  inputDelimiters?: string[];
}

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface UseInputDelimiterParams<Option, IsMulti extends boolean, Group extends GroupBase<Option>>
  extends Pick<CreatableSelectProps<Option, IsMulti, Group>, 'isMulti' | 'onInputChange' | 'inputDelimiters'> {}

/**
 * CreatableSelect 専用: 入力時にデリミターが含まれると複数オプションに分割して追加する
 */
function useInputDelimiter<Option, IsMulti extends boolean, Group extends GroupBase<Option>>({
  isMulti,
  inputDelimiters = DEFAULT_DELIMITERS,
  onInputChange,
}: UseInputDelimiterParams<Option, IsMulti, Group>) {
  const [inputValue, setInputValue] = useState('');
  const selectInstanceRef = useRef<SelectInstance<Option, IsMulti, Group> | null>(null);

  const handleInputChange = useCallback(
    (newValue: string, meta: InputActionMeta) => {
      const delimiters = inputDelimiters ?? DEFAULT_DELIMITERS;
      const enabled = isMulti && delimiters.length > 0;
      const selectInstance = selectInstanceRef.current;

      if (selectInstance === null) {
        setInputValue(newValue);
        onInputChange?.(newValue, meta);
        return;
      }

      if (!enabled || meta.action !== 'input-change') {
        setInputValue(newValue);
        onInputChange?.(newValue, meta);
        return;
      }

      const hasDelimiter = delimiters.some((d) => newValue.includes(d));
      if (!hasDelimiter) {
        setInputValue(newValue);
        onInputChange?.(newValue, meta);
        return;
      }

      const items = splitByDelimiters(newValue, delimiters);
      if (items.length === 0) {
        setInputValue('');
        onInputChange?.('', meta);
        return;
      }

      const trimmedValue = newValue.trimEnd();
      const endsWithDelimiter = delimiters.some((d) => trimmedValue.endsWith(d));
      const itemsToAdd = endsWithDelimiter ? items : items.slice(0, -1);
      const remainingInput = endsWithDelimiter ? '' : (items[items.length - 1] ?? '');

      const currentValue = selectInstance.getValue();
      const existingValues = new Set(currentValue.map((o) => selectInstance.getOptionValue(o)));

      const newOptions = itemsToAdd
        .filter((item) => !existingValues.has(item))
        .map((item) => ({ label: item, value: item }) as unknown as Option);
      for (const o of newOptions) {
        existingValues.add(selectInstance.getOptionValue(o));
      }
      const mergedValue = [...currentValue, ...newOptions];

      // setValueで内部状態を更新（onChangeも自動で呼ばれる）
      type SetValueArg = Parameters<SelectInstance<Option, IsMulti, Group>['setValue']>[0];
      selectInstance.setValue(mergedValue as unknown as SetValueArg, 'select-option');

      setInputValue(remainingInput);
      onInputChange?.(remainingInput, meta);
    },
    [isMulti, inputDelimiters, onInputChange, selectInstanceRef]
  );

  return { selectInstanceRef, inputValue, onInputChange: handleInputChange };
}

const CreatableSelect = <Option, IsMulti extends boolean, Group extends GroupBase<Option>>(
  {
    styles,
    components,
    className,
    inputDelimiters,
    onInputChange,
    value,
    onChange,
    options = EMPTY_OPTIONS,
    isMulti,
    ...props
  }: CreatableSelectProps<Option, IsMulti, Group>,
  ref: React.ForwardedRef<SelectInstance<Option, IsMulti, Group>>
) => {
  const {
    selectInstanceRef,
    inputValue,
    onInputChange: handleInputChange,
  } = useInputDelimiter({
    isMulti,
    inputDelimiters,
    onInputChange,
  });

  const delimiterProps =
    isMulti && (inputDelimiters === undefined || inputDelimiters.length > 0)
      ? { inputValue, onInputChange: handleInputChange }
      : {};

  const setRefs = useMergeRefs(
    ref,
    selectInstanceRef as unknown as React.RefObject<SelectInstance<Option, IsMulti, Group>>
  );

  return (
    <Select
      ref={setRefs}
      components={{
        IndicatorSeparator: () => null,
        DropdownIndicator,
        ...components,
      }}
      className={classnames('acms-admin-rich-select', className)}
      classNames={{
        menuPortal: () => 'acms-admin-rich-select-menu-portal',
      }}
      styles={
        {
          ...stylesConfig,
          ...styles,
        } as StylesConfig<Option, IsMulti, Group>
      }
      value={value}
      onChange={onChange}
      options={options}
      onInputChange={onInputChange}
      isMulti={isMulti}
      {...props}
      {...delimiterProps}
    />
  );
};

CreatableSelect.displayName = 'CreatableSelect';

export default forwardRef(CreatableSelect) as <
  Option = unknown,
  IsMulti extends boolean = false,
  Group extends GroupBase<Option> = GroupBase<Option>,
>(
  props: CreatableSelectProps<Option, IsMulti, Group> & { ref?: React.Ref<SelectInstance<Option, IsMulti, Group>> }
) => JSX.Element;
