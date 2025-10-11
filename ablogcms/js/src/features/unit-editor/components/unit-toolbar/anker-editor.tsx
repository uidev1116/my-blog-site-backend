import { useState } from 'react';
import { ButtonV2 } from '@components/button-v2';
import VStack from '@components/stack/v-stack';
import { Popover, PopoverContent, PopoverTrigger } from '@components/popover';
import { MenuItem } from '@components/dropdown-menu';
import { Icon } from '@components/icon';
import { useUnitToolbarProps } from './store';

const AnkerEditor = () => {
  const { editor, unit } = useUnitToolbarProps();

  const [isOpen, setIsOpen] = useState(false);

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    if (!(event.target instanceof HTMLFormElement)) {
      return;
    }
    const formData = new FormData(event.target);
    editor.commands.setUnitAnker(unit.id, formData.get(`unit-anker-${unit.id}`) as string);
    setIsOpen(false);
  };

  return (
    <Popover isOpen={isOpen} onOpenChange={setIsOpen} modal placement="left">
      <PopoverTrigger asChild>
        <MenuItem icon={<Icon name="grid_3x3" />} onSelect={() => setIsOpen((prev) => !prev)} closeOnSelect={false}>
          {unit.anker || 'IDを設定'}
          {unit.anker && (
            <span style={{ marginLeft: 'auto' }}>
              <Icon name="edit" />
            </span>
          )}
        </MenuItem>
      </PopoverTrigger>
      <PopoverContent data-elevation="3">
        <div>
          <form onSubmit={handleSubmit} className="acms-admin-form">
            <VStack spacing="0.5rem" align="start">
              <VStack align="start" className="acms-admin-width-max">
                <label htmlFor={`input-text-unit-anker-${unit.id}`}>IDを設定</label>
                <input
                  id={`input-text-unit-anker-${unit.id}`}
                  name={`unit-anker-${unit.id}`}
                  type="text"
                  pattern="^[a-zA-Z0-9_\-]+$"
                  className="acms-admin-form-width-full"
                  defaultValue={unit.anker}
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

export default AnkerEditor;
