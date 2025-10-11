import { useState } from 'react';
import { Icon } from '@features/block-editor/components/ui/Icon';
import { ButtonV2 } from '@components/button-v2';
import { Popover, PopoverContent, PopoverTrigger } from '@components/popover';
import { MenuItem } from '@components/dropdown-menu';
import VStack from '@components/stack/v-stack';

export type IdEditorProps = {
  onChange: (value: string) => void;
  value: string;
};

export const IdEditor = ({ onChange, value }: IdEditorProps) => {
  const [isOpen, setIsOpen] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!(e.target instanceof HTMLFormElement)) {
      return;
    }
    const formData = new FormData(e.target);
    const id = formData.get('id') as string;
    setIsOpen(false);
    onChange(id);
  };

  return (
    <Popover isOpen={isOpen} onOpenChange={setIsOpen} modal placement="right">
      <PopoverTrigger asChild>
        <MenuItem icon={<Icon name="grid_3x3" />} onSelect={() => setIsOpen((prev) => !prev)} closeOnSelect={false}>
          {value || 'IDを設定'}
          {value && (
            <span style={{ marginLeft: 'auto' }}>
              <Icon name="edit" />
            </span>
          )}
        </MenuItem>
      </PopoverTrigger>
      <PopoverContent data-elevation="3" className="acms-admin-block-editor-id-editor">
        <div>
          <form onSubmit={handleSubmit} className="acms-admin-form">
            <VStack spacing="0.5rem" align="start">
              <VStack align="start" className="acms-admin-width-max">
                <label htmlFor="input-text-id-editor">IDを設定</label>
                <input
                  id="input-text-id-editor"
                  name="id"
                  pattern="^[a-zA-Z0-9_\-]+$"
                  type="text"
                  className="acms-admin-form-width-full"
                  defaultValue={value}
                  onKeyDown={(e) => e.stopPropagation()}
                />
              </VStack>
              <ButtonV2 variant="filled" size="small" type="submit">
                適用
              </ButtonV2>
            </VStack>
          </form>
        </div>
      </PopoverContent>
    </Popover>
  );
};
