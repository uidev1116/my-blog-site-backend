import styled from 'styled-components';
import { Component } from 'react';
import { AdminCardLaneType } from '../../types';
import VisuallyHidden from '../../../../components/visually-hidden';

const RemoveBtn = styled.button`
  position: absolute;
  top: 8px;
  right: 5px;
  font-size: 16px;
  color: #b9b9b9;
  appearance: none;
  background-color: transparent;
  border: none;

  &:hover {
    color: #797979;
  }
`;

const TitleToggleButton = styled.button`
  position: relative;
  display: inline-block;
  padding: 0;
  font-size: 11px;
  font-weight: bold;
  line-height: 1.3;
  color: inherit;
  text-align: left;
  background: transparent;
  border: none;
  outline: none;
  transition: background-color linear 0.15s;

  &:disabled {
    opacity: 1;
  }
`;

interface AdminLaneHeaderProps extends AdminCardLaneType {
  titleStyle: React.CSSProperties;
  addLane: () => void;
  removeLane: (id: string) => void;
  doneEdit: (lane: AdminCardLaneType) => void;
}

type AdminLaneHeaderState = {
  editMode: boolean;
  title: string;
};

export default class AdminLaneHeader extends Component<AdminLaneHeaderProps, AdminLaneHeaderState> {
  constructor(props: AdminLaneHeaderProps) {
    super(props);
    this.state = {
      editMode: false,
      title: props.title,
    };
  }

  edit = () => {
    this.setState({
      editMode: true,
    });
  };

  doneEdit = () => {
    const { id, index, cards, doneEdit } = this.props;
    const { title } = this.state;
    this.setState({
      editMode: false,
    });
    doneEdit({
      id,
      index,
      title,
      cards,
    });
  };

  addLane = () => {
    const { addLane } = this.props;
    addLane();
  };

  removeLane(id: string) {
    const { removeLane } = this.props;
    removeLane(id);
  }

  render() {
    const { titleStyle, index, id, movable } = this.props;

    const { editMode, title } = this.state;
    return (
      <div>
        {movable !== false && <span className="acms-admin-icon-sort react-trello-lane-sort" />}
        {index !== 0 && (
          <RemoveBtn
            type="button"
            onClick={() => {
              this.removeLane(id);
            }}
            aria-label={ACMS.i18n('admin_menu.remove_lane')}
          >
            ×
          </RemoveBtn>
        )}
        {!editMode && (
          <TitleToggleButton style={titleStyle} type="button" disabled={index === 0} onClick={this.edit}>
            <span className="react-trello-lane-title-inner">
              {title}
              {index !== 0 && <span className="acms-admin-icon-control-edit react-trello-lane-title-edit" />}
            </span>
          </TitleToggleButton>
        )}
        {editMode && (
          <label>
            <input
              type="text"
              value={title}
              onChange={(event: React.ChangeEvent<HTMLInputElement>) => {
                this.setState({
                  title: event.target.value,
                });
              }}
              onKeyDown={(event: React.KeyboardEvent<HTMLInputElement>) => {
                if (event.key === 'Enter' && !event.nativeEvent.isComposing) {
                  event.preventDefault();
                  this.doneEdit();
                }
              }}
            />
            <VisuallyHidden>{ACMS.i18n('admin_menu.lane_title')}</VisuallyHidden>
          </label>
        )}
        {index === 0 && (
          <button
            type="button"
            className="acms-admin-btn acms-admin-btn-success"
            onClick={this.addLane}
            style={{ position: 'absolute', top: '5px', right: '10px' }}
          >
            {ACMS.i18n('admin_menu.add_lane')}
          </button>
        )}
        {editMode && (
          <button type="button" className="acms-admin-btn react-trello-lane-done-btn" onClick={this.doneEdit}>
            {ACMS.i18n('admin_menu.complete')}
          </button>
        )}
      </div>
    );
  }
}
