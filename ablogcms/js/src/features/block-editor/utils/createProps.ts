import { datasetToProps } from '../../../utils/react';
import type { BlockEditor } from '../components/BlockEditor';

type BlockEditorProps = React.ComponentPropsWithoutRef<typeof BlockEditor>;

interface CreatePropsOptions extends Partial<BlockEditorProps> {
  onInputChange?: (newValue: string) => void;
}

export default function createProps(element: HTMLElement, options: CreatePropsOptions = {}) {
  const { html } = element.dataset;

  if (html === undefined) {
    throw new Error('Not found data-html attribute!');
  }

  const input = element.querySelector<HTMLInputElement>(html);

  if (!input) {
    throw new Error('Not found input element!');
  }

  return {
    defaultValue: input.value,
    ...ACMS.Config.blockEditorConfig.editorProps,
    ...datasetToProps<Partial<BlockEditorProps>>(element.dataset),
    ...options,
    onUpdate: (newValue: string) => {
      if (options.onUpdate) {
        options.onUpdate(newValue);
      }
      input.value = newValue;
      if (options.onInputChange) {
        options.onInputChange(newValue);
      }
    },
  };
}
