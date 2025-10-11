import styled from 'styled-components';
import { Component } from 'react';
import IconPicker from '../../../../components/icon-picker/icon-picker';
import HStack from '../../../../components/stack/h-stack';

interface AdminMenuCardState {
  title: string;
  label: string;
  icon: string;
  url: string;
  deletable: string;
}

interface AdminNewCardProps {
  laneId: string;
  onAdd(state: AdminMenuCardState): void;
  onCancel(): void;
}

const AdminCardInner = styled.div`
  padding: 5px;
`;

const AdminCardRow = styled.div`
  margin-bottom: 8px;
`;

const AdminCard = styled.div`
  position: relative;
  min-width: 220px;
  padding: 5px;
  margin: 1px 2px 7px;
  cursor: pointer;
  background-color: #fff;
  border-radius: 2px;
  box-shadow: 0 0 6px rgb(0 0 0 / 20%);

  &:hover {
    background: #fff;
  }
`;

export default class AdminNewCard extends Component<AdminNewCardProps, AdminMenuCardState> {
  add = () => {
    const { url, title } = this.state;
    const { onAdd } = this.props;
    if (url && title) {
      onAdd({ ...this.state });
      return;
    }
    alert(ACMS.i18n('admin_menu.card_add_alert'));
  };

  handleClick = () => {
    this.add();
  };

  render() {
    const { onCancel, laneId } = this.props;
    return (
      <AdminCard>
        <div style={{ whiteSpace: 'normal' }} className="acms-admin-form react-trello-new">
          <AdminCardInner>
            <AdminCardRow>
              <span className="react-trello-card-item-name">{ACMS.i18n('admin_menu.add_icon')}</span>
              <div>
                <IconPicker
                  onChange={(icon) => {
                    this.setState({ icon });
                  }}
                />
              </div>
            </AdminCardRow>
            <AdminCardRow>
              <label htmlFor={`input-text-admin-menu-add-title-${laneId}`} className="react-trello-card-item-name">
                {ACMS.i18n('admin_menu.add_title')}
              </label>
              <div>
                <input
                  id={`input-text-admin-menu-add-title-${laneId}`}
                  type="text"
                  placeholder={ACMS.i18n('admin_menu.add_title')}
                  className="acms-admin-form-width-full"
                  onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                    this.setState({
                      title: event.target.value,
                    });
                  }}
                  onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
                    if (event.key === 'Enter' && !event.nativeEvent.isComposing) {
                      event.preventDefault();
                      this.add();
                    }
                  }}
                />
              </div>
            </AdminCardRow>
            <AdminCardRow>
              <label htmlFor={`input-text-admin-menu-url-${laneId}`} className="react-trello-card-item-name">
                {ACMS.i18n('admin_menu.url')}
              </label>
              <div>
                <input
                  id={`input-text-admin-menu-url-${laneId}`}
                  type="url"
                  placeholder="URL"
                  className="acms-admin-form-width-full"
                  onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                    this.setState({
                      url: event.target.value,
                    });
                  }}
                  onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
                    if (event.key === 'Enter' && !event.nativeEvent.isComposing) {
                      event.preventDefault();
                      this.add();
                    }
                  }}
                />
              </div>
            </AdminCardRow>
            <HStack justify="end">
              <button type="button" className="acms-admin-btn acms-admin-btn-text" onClick={onCancel}>
                {ACMS.i18n('admin_menu.cancel')}
              </button>
              <button
                type="button"
                className="acms-admin-btn acms-admin-btn-success react-trello-card-add-btn"
                onClick={this.handleClick}
              >
                {ACMS.i18n('admin_menu.add')}
              </button>
            </HStack>
          </AdminCardInner>
        </div>
      </AdminCard>
    );
  }
}
