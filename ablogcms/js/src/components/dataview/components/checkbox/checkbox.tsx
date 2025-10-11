import { forwardRef, useId } from 'react';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface CheckboxProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'id'> {}

const Checkbox = forwardRef<HTMLInputElement, CheckboxProps>(({ ...props }, ref) => {
  const id = useId();
  return (
    <div className="acms-admin-form-checkbox acms-admin-m-0">
      <label htmlFor={id}>
        <input ref={ref} id={id} type="checkbox" {...props} />
        <i className="acms-admin-ico-checkbox acms-admin-m-0" />
      </label>
    </div>
  );
});

export default Checkbox;
