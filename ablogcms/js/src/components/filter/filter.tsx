import { forwardRef } from 'react';
import classnames from 'classnames';

interface FilterProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const Filter = forwardRef<HTMLDivElement, FilterProps>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter', className)} {...props}>
      {children}
    </div>
  );
});

Filter.displayName = 'Filter';

interface FilterBodyProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterBody = forwardRef<HTMLDivElement, FilterBodyProps>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter-body', className)} {...props}>
      {children}
    </div>
  );
});

FilterBody.displayName = 'FilterBody';

interface FilterFooterProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterFooter = forwardRef<HTMLDivElement, FilterFooterProps>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter-footer', className)} {...props}>
      {children}
    </div>
  );
});

FilterFooter.displayName = 'FilterFooter';

interface FilterInnerProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterInner = forwardRef<HTMLDivElement, FilterInnerProps>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter-inner', className)} {...props}>
      {children}
    </div>
  );
});

FilterInner.displayName = 'FilterInner';

interface FilterGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterGroup = forwardRef<HTMLDivElement, FilterGroupProps>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter-group', className)} {...props}>
      {children}
    </div>
  );
});

FilterGroup.displayName = 'FilterGroup';

interface FilterGroupV2Props extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterGroupV2 = forwardRef<HTMLDivElement, FilterGroupV2Props>(({ children, className, ...props }, ref) => {
  return (
    <div ref={ref} className={classnames('acms-admin-filter-group-v2', className)} {...props}>
      {children}
    </div>
  );
});

FilterGroupV2.displayName = 'FilterGroupV2';

interface FilterGroupTitleProps extends React.HTMLAttributes<HTMLParagraphElement> {
  children?: React.ReactNode;
}

const FilterGroupTitle = forwardRef<HTMLParagraphElement, FilterGroupTitleProps>(
  ({ children, className, ...props }, ref) => {
    return (
      <p ref={ref} className={classnames('acms-admin-filter-label', className)} {...props}>
        {children}
      </p>
    );
  }
);

FilterGroupTitle.displayName = 'FilterGroupTitle';

interface FilterContentProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
  fit?: boolean;
}

const FilterContent = forwardRef<HTMLDivElement, FilterContentProps>(
  ({ children, className, fit = false, ...props }, ref) => {
    return (
      <div
        ref={ref}
        className={classnames('acms-admin-filter-content', className, { 'acms-admin-filter-content-fit': fit })}
        {...props}
      >
        {children}
      </div>
    );
  }
);

FilterContent.displayName = 'FilterContent';

interface FilterItemProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
  full?: boolean;
}

const FilterItem = forwardRef<HTMLDivElement, FilterItemProps>(
  ({ children, className, full = false, ...props }, ref) => {
    return (
      <div
        ref={ref}
        className={classnames('acms-admin-filter-item', className, { 'acms-admin-filter-item-full': full })}
        {...props}
      >
        {children}
      </div>
    );
  }
);

FilterItem.displayName = 'FilterItem';

interface FilterItemLabelProps extends React.LabelHTMLAttributes<HTMLLabelElement> {
  children?: React.ReactNode;
}

const FilterItemLabel = forwardRef<HTMLLabelElement, FilterItemLabelProps>(({ children, className, ...props }, ref) => {
  return (
    <label ref={ref} className={classnames('acms-admin-filter-item-name', className)} {...props}>
      {children}
    </label>
  );
});

FilterItemLabel.displayName = 'FilterItemLabel';

interface FilterActionGroupProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterActionGroup = forwardRef<HTMLDivElement, FilterActionGroupProps>(
  ({ children, className, ...props }, ref) => {
    return (
      <div ref={ref} className={classnames('acms-admin-filter-action-group', className)} {...props}>
        {children}
      </div>
    );
  }
);

FilterActionGroup.displayName = 'FilterActionGroup';

interface FilterFormControlProps extends React.HTMLAttributes<HTMLDivElement> {
  children?: React.ReactNode;
}

const FilterFormControl = forwardRef<HTMLDivElement, FilterFormControlProps>(
  ({ children, className, ...props }, ref) => {
    return (
      <div ref={ref} className={classnames('acms-admin-filter-form-control', className)} {...props}>
        {children}
      </div>
    );
  }
);

FilterFormControl.displayName = 'FilterFormControl';

interface FilterDetailButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  children?: React.ReactNode;
}

const FilterDetailButton = forwardRef<HTMLButtonElement, FilterDetailButtonProps>(
  ({ children, className, ...props }, ref) => {
    return (
      <button type="button" ref={ref} className={classnames('acms-admin-filter-detail-btn', className)} {...props}>
        <span className="acms-admin-icon-arrow-right acms-admin-margin-right-mini" />
        {children}
      </button>
    );
  }
);

FilterDetailButton.displayName = 'FilterDetailButton';

// eslint-disable-next-line @typescript-eslint/no-empty-object-type
interface FilterToggleButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {}

const FilterToggleButton = forwardRef<HTMLButtonElement, FilterToggleButtonProps>(({ className, ...props }, ref) => {
  return (
    <button type="button" ref={ref} className={classnames('acms-admin-filter-toggle-btn', className)} {...props}>
      <span className="material-symbols-outlined" aria-hidden="true" />
    </button>
  );
});

FilterToggleButton.displayName = 'FilterToggleButton';

export {
  Filter,
  FilterBody,
  FilterFooter,
  FilterInner,
  FilterGroup,
  FilterGroupV2,
  FilterGroupTitle,
  FilterContent,
  FilterItem,
  FilterItemLabel,
  FilterActionGroup,
  FilterFormControl,
  FilterDetailButton,
  FilterToggleButton,
};
