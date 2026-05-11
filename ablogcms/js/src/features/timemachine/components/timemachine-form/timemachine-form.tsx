import { useCallback } from 'react';
import TimePicker from '../../../../components/time-picker/time-picker';
import DatePicker from '../../../../components/date-picker/date-picker';

import type { RuleType, TimeMachineState } from '../../types';

const EMPTY_RULES: RuleType[] = [];

interface TimeMachineFormProps {
  state: TimeMachineState;
  rules?: RuleType[];
  onChange?: (state: TimeMachineState) => void;
  onSubmit?: () => void;
}

const TimeMachineForm = ({
  state,
  rules = EMPTY_RULES,
  onChange = () => {},
  onSubmit = () => {},
}: TimeMachineFormProps) => {
  const handleDateChange = (_dates: Date[], date: string) => {
    onChange({ ...state, date });
  };

  const handleTimeChange = (_dates: Date[], time: string) => {
    onChange({ ...state, time });
  };

  const handleRuleChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
    onChange({ ...state, ruleId: parseInt(event.target.value, 10) });
  };

  const handleClick = useCallback(() => {
    onSubmit();
  }, [onSubmit]);

  return (
    <div className="acms-admin-timemachine-form">
      <div>
        <DatePicker value={state.date} onChange={handleDateChange} />
      </div>
      <div>
        <TimePicker value={state.time} onChange={handleTimeChange} />
      </div>
      <div>
        <select value={state.ruleId} onChange={handleRuleChange}>
          <option value={0}>ルールなし</option>
          {rules.map((rule) => (
            <option value={rule.id} key={rule.id}>
              {rule.label}
            </option>
          ))}
        </select>
      </div>
      <div>
        <button type="button" className="acms-admin-btn-admin acms-admin-text-nowrap" onClick={handleClick}>
          {ACMS.i18n('preview.change')}
        </button>
      </div>
    </div>
  );
};

export default TimeMachineForm;
