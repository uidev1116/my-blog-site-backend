interface UnitStatusProps {
  label: string;
  value: string;
}

const UnitStatus = ({ label, value }: UnitStatusProps) => {
  return (
    <div className="acms-admin-unit-toolbar-status">
      <span>{label}</span>
      <span>{value}</span>
    </div>
  );
};

export default UnitStatus;
