import { useState, useEffect } from 'react';
import { throttle } from 'throttle-debounce';
import Modal from '../../../../components/modal/modal';
import useEffectOnce from '../../../../hooks/use-effect-once';
import { checkEntryLock, lockEntry } from '../../api';
import HStack from '../../../../components/stack/h-stack';

const EntryLockModal = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [isNoteOpen, setIsNoteOpen] = useState(false);
  const [lockedInfo, setLockedInfo] = useState({
    name: '',
    icon: '',
    datetime: '',
    expire: '',
    viewLink: '',
    alertOnly: false,
  });

  const fetchLockedInfo = async () => {
    const json = await checkEntryLock();
    if (json.locked) {
      setIsOpen(true);
      setLockedInfo({
        name: json.name,
        icon: json.icon,
        datetime: json.datetime,
        expire: json.expire,
        viewLink: json.viewLink,
        alertOnly: json.alertOnly,
      });
    }
  };

  const handleNoteButtonClick = (e: React.MouseEvent<HTMLButtonElement>) => {
    e.preventDefault();
    setIsNoteOpen(!isNoteOpen);
  };

  useEffect(() => {
    fetchLockedInfo();
  }, [setIsOpen]);

  useEffectOnce(() => {
    const handleChange = throttle(10000, async () => {
      await lockEntry();
    });

    const events = [
      'change',
      'input',
      'acmsAdminCategoryChange',
      'acmsAdminSubCategoryChange',
      'acmsAdminTagChange',
      'acmsAdminMediaUnitChange',
      'acmsAdminMediaFieldChange',
      'acmsAdminRelatedEntryChange',
    ];
    const entryEditForm = document.querySelector('#entryForm');
    if (entryEditForm) {
      events.forEach((event) => {
        entryEditForm.addEventListener(event, handleChange);
      });
    }

    return () => {
      if (entryEditForm) {
        events.forEach((event) => {
          entryEditForm.removeEventListener(event, handleChange);
        });
      }
    };
  });

  const modalFooter = lockedInfo.alertOnly ? (
    <HStack display="inline-flex">
      <a href={lockedInfo.viewLink} className="acms-admin-btn">
        {ACMS.i18n('entry_lock.modal.viewLink')}
      </a>
      <button
        onClick={() => {
          setIsOpen(false);
        }}
        type="button"
        className="acms-admin-btn acms-admin-btn-admin-primary acms-admin-btn-admin-search"
      >
        {ACMS.i18n('entry_lock.modal.editLink')}
      </button>
    </HStack>
  ) : (
    <div>
      <a href={lockedInfo.viewLink} className="acms-admin-btn acms-admin-btn-admin-info acms-admin-btn-admin-search">
        {ACMS.i18n('entry_lock.modal.viewLink')}
      </a>
    </div>
  );

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => {
        setIsOpen(false);
      }}
      size="small"
      dialogStyle={{ marginTop: '100px' }}
      role="alertdialog"
      aria-labelledby="acms-admin-entry-lock-modal-title"
      aria-describedby="acms-admin-entry-lock-modal-description"
    >
      <Modal.Header>{ACMS.i18n('entry_lock.modal.title')}</Modal.Header>
      <Modal.Body>
        <div className="acms-admin-entry-lock-modal">
          <p id="acms-admin-entry-lock-modal-description" className="acms-admin-entry-lock-modal-lead">
            {lockedInfo.alertOnly && ACMS.i18n('entry_lock.modal.lead1')}
            {!lockedInfo.alertOnly && ACMS.i18n('entry_lock.modal.lead2')}
          </p>
          <table className="acms-admin-entry-lock-modal-table acms-admin-table-admin-edit">
            <tbody>
              <tr>
                <th>{ACMS.i18n('entry_lock.modal.nameLabel')}</th>
                <td>
                  <div className="acms-admin-entry-lock-modal-user">
                    <img
                      src={`${ACMS.Config.root}archives/${lockedInfo.icon}`}
                      width="24"
                      height="24"
                      className="acms-admin-user"
                      alt={lockedInfo.name}
                    />
                    <span>{lockedInfo.name}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <th>{ACMS.i18n('entry_lock.modal.editDateLabel')}</th>
                <td>{lockedInfo.datetime}</td>
              </tr>
              {!lockedInfo.alertOnly && (
                <tr>
                  <th>{ACMS.i18n('entry_lock.modal.lockExpireLabel')}</th>
                  <td>{lockedInfo.expire}</td>
                </tr>
              )}
            </tbody>
          </table>
          {lockedInfo.alertOnly && (
            <p className="acms-admin-entry-lock-modal-lead">{ACMS.i18n('entry_lock.modal.lead3')}</p>
          )}
          {lockedInfo.alertOnly && (
            <button type="button" onClick={handleNoteButtonClick} className="acms-admin-entry-lock-modal-note-label">
              <i className="acms-admin-icon-tooltip" />
              <span>{ACMS.i18n('entry_lock.modal.noteLabel')}</span>
            </button>
          )}
          {lockedInfo.alertOnly && isNoteOpen && (
            <p className="acms-admin-entry-lock-modal-note">{ACMS.i18n('entry_lock.modal.note')}</p>
          )}
        </div>
      </Modal.Body>
      <Modal.Footer>{modalFooter}</Modal.Footer>
    </Modal>
  );
};

export default EntryLockModal;
