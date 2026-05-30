import { describe, it, expect } from 'vitest';
import AcmsFieldList from './acmsField';
import { type AcmsField } from './types';

describe('AcmsFieldList', () => {
  it('should initialize with an empty list', () => {
    const fieldList = new AcmsFieldList();
    expect(fieldList.getFields()).toEqual([]);
  });

  it('should initialize with a given list of fields', () => {
    const fields: AcmsField[] = [{ key: 'title', filters: [{ value: 'test', operator: 'eq', connector: 'and' }] }];
    const fieldList = new AcmsFieldList(fields);
    expect(fieldList.getFields()).toEqual(fields);
  });

  it('should push a new field to the list', () => {
    const fieldList = new AcmsFieldList();
    const field: AcmsField = { key: 'title', filters: [{ value: 'test', operator: 'eq', connector: 'and' }] };
    fieldList.push(field);
    expect(fieldList.getFields()).toContain(field);
  });

  it('should pop a field from the list', () => {
    const field: AcmsField = { key: 'title', filters: [{ value: 'test', operator: 'eq', connector: 'and' }] };
    const fieldList = new AcmsFieldList([field]);
    const popped = fieldList.pop();
    expect(popped).toEqual(field);
    expect(fieldList.getFields()).toHaveLength(0);
  });

  it('should shift a field from the list', () => {
    const field1: AcmsField = { key: 'title', filters: [{ value: 'test', operator: 'eq', connector: 'and' }] };
    const field2: AcmsField = { key: 'description', filters: [{ value: 'sample', operator: 'neq', connector: 'or' }] };
    const fieldList = new AcmsFieldList([field1, field2]);
    const shifted = fieldList.shift();
    expect(shifted).toEqual(field1);
    expect(fieldList.getFields()).toEqual([field2]);
  });

  it('should unshift a new field to the list', () => {
    const field1: AcmsField = { key: 'title', filters: [{ value: 'test', operator: 'eq', connector: 'and' }] };
    const field2: AcmsField = { key: 'description', filters: [{ value: 'sample', operator: 'neq', connector: 'or' }] };
    const fieldList = new AcmsFieldList([field1]);
    fieldList.unshift(field2);
    expect(fieldList.getFields()).toEqual([field2, field1]);
  });

  it('should serialize fields with simple operators', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [
          { value: '1000', operator: 'gte', connector: 'and' },
          { value: '500', operator: 'lt', connector: 'or' },
        ],
        separator: '_and_',
      },
    ];
    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/1000/or/lt/500');
  });

  it('should handle multiple fields with separators', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [{ value: '1000', operator: 'gte', connector: 'and' }],
        separator: '_and_',
      },
      {
        key: 'color',
        filters: [{ value: 'red', operator: 'eq', connector: 'and' }],
        separator: '_or_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/1000/_or_/color/eq/red');
  });

  it('should serialize fields with empty values for certain operators', () => {
    const fields: AcmsField[] = [
      {
        key: 'status',
        filters: [{ value: '', operator: 'em', connector: 'and' }],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('status/em');
  });

  it('should handle the case where multiple filters with different connectors exist', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [
          { value: '300', operator: 'gte', connector: 'and' },
          { value: '150', operator: 'lte', connector: 'or' },
        ],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/300/or/lte/150');
  });

  it('should handle empty fields correctly', () => {
    const fields: AcmsField[] = [];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('');
  });

  it('should remove unnecessary separators at the beginning', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [{ value: '1000', operator: 'gte', connector: 'and' }],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/1000');
  });

  it('should remove unnecessary separators at the end', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [{ value: '1000', operator: 'gte', connector: 'and' }],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/1000');
  });

  it('should handle empty values for certain operators', () => {
    const fields: AcmsField[] = [
      {
        key: 'status',
        filters: [{ value: '', operator: 'em', connector: 'and' }],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('status/em');
  });

  it('should handle the case where multiple filters with different connectors exist', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [
          { value: '300', operator: 'gte', connector: 'and' },
          { value: '150', operator: 'lte', connector: 'or' },
        ],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('price/gte/300/or/lte/150');
  });

  it('should handle empty fields correctly', () => {
    const fields: AcmsField[] = [];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('');
  });

  // マルチバイト文字列のテスト
  it('should handle multi-byte characters', () => {
    const fields: AcmsField[] = [
      {
        key: 'prefecture',
        filters: [{ value: '愛知県', operator: 'eq', connector: 'and' }],
        separator: '_and_',
      },
    ];

    const field = new AcmsFieldList(fields);
    const result = field.toString();
    expect(result).toBe('prefecture/eq/愛知県');
  });

  it('should handle empty fields gracefully', () => {
    const fieldList = new AcmsFieldList();
    expect(fieldList.toString()).toBe('');
  });

  // ---------------------------------------------------------------------------
  // serialize の and / or コネクター省略仕様（PHP 側 Field_Search と整合）
  //
  // 仕様: connector='and' は serialize 出力に現れない。a-blog cms の URL では
  // `and` は同一フィールド内の暗黙のデフォルト connector として扱われ、parse
  // 側でも `and` トークンは separator 経路に流れる。出力で裸の `and` を残すと
  // parse 側の意味と衝突するため、serialize は `and` を必ず省略する。一方
  // `or` は明示的に出力する必要がある。
  // ---------------------------------------------------------------------------

  it('serialize: and コネクターは出力で省略される', () => {
    const fields: AcmsField[] = [
      {
        key: 'color',
        filters: [{ value: 'red', operator: 'lk', connector: 'and' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('color/lk/red');
  });

  it('serialize: or コネクターは出力に明示される', () => {
    const fields: AcmsField[] = [
      {
        key: 'color',
        filters: [{ value: 'red', operator: 'lk', connector: 'or' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('color/or/lk/red');
  });

  it('serialize: and コネクターと eq 演算子の組み合わせは eq のみ残る', () => {
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [{ value: '200', operator: 'eq', connector: 'and' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('price/eq/200');
  });

  it('serialize: or コネクターと eq 演算子の組み合わせは両方省略される', () => {
    // or + eq の組合せでは or も eq も省略され、値のみが出る
    const fields: AcmsField[] = [
      {
        key: 'price',
        filters: [{ value: '200', operator: 'eq', connector: 'or' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('price/200');
  });

  it('serialize: and コネクターでの em 演算子は em のみ出力する', () => {
    const fields: AcmsField[] = [
      {
        key: 'flag',
        filters: [{ value: '', operator: 'em', connector: 'and' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('flag/em');
  });

  it('serialize: or コネクターでの em 演算子は or を伴って出力する', () => {
    const fields: AcmsField[] = [
      {
        key: 'flag',
        filters: [{ value: '', operator: 'em', connector: 'or' }],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('flag/or/em');
  });

  it('serialize: and コネクターは連続値でも省略される', () => {
    // 同一フィールドに複数値があり、すべて connector='and' の場合、
    // 各値の前にあるはずの 'and' は出力に現れない
    const fields: AcmsField[] = [
      {
        key: 'color',
        filters: [
          { value: 'red', operator: 'lk', connector: 'and' },
          { value: 'blue', operator: 'lk', connector: 'and' },
        ],
        separator: '_and_',
      },
    ];
    expect(new AcmsFieldList(fields).toString()).toBe('color/lk/red/lk/blue');
  });

  it('should parse single field and value', () => {
    const input = 'price/100';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'eq',
            value: '100',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse multiple values for the same field', () => {
    const input = 'price/200/300/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'eq',
            value: '200',
            connector: 'or',
          },
          {
            operator: 'eq',
            value: '300',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('演算子が eq の場合、operator は強制的に or になる', () => {
    // 裸の `and` は parse で separator として扱われるため、入力に書かない（PHP 側と仕様を揃えた結果）。
    const input = 'price/eq/200/eq/300/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'eq',
            value: '200',
            connector: 'or',
          },
          {
            operator: 'eq',
            value: '300',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse operators like lte', () => {
    const input = 'price/lte/300/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'lte',
            value: '300',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse multiple operators', () => {
    const input = 'price/gte/300/lte/100/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'gte',
            value: '300',
            connector: 'and',
          },
          {
            operator: 'lte',
            value: '100',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse single operator', () => {
    const input = 'price/neq/200/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'neq',
            value: '200',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse connector', () => {
    const input = 'price/or/gte/300/or/lte/150/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'gte',
            value: '300',
            connector: 'or',
          },
          {
            operator: 'lte',
            value: '150',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should handle complex expressions', () => {
    const input = 'price/or/lt/100/100/or/nem//or/gt/300/';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'lt',
            value: '100',
            connector: 'or',
          },
          {
            operator: 'eq',
            value: '100',
            connector: 'or',
          },
          {
            operator: 'nem',
            value: '',
            connector: 'or',
          },
          {
            operator: 'eq',
            value: '',
            connector: 'or',
          },
          {
            operator: 'gt',
            value: '300',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse "_and_" separators', () => {
    const input = 'price/gte/1000/_and_/color/red/_and_/type/stationery';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'gte',
            value: '1000',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
      {
        key: 'color',
        filters: [
          {
            operator: 'eq',
            value: 'red',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
      {
        key: 'type',
        filters: [
          {
            operator: 'eq',
            value: 'stationery',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should parse "_or_" separators', () => {
    // connector='and' は省略形なので、`color/and/lk/red/...` ではなく `color/lk/red/...` と書く。
    // 裸の `and` は parse 側で separator として扱われるため、connector を明示したい場合は省略する仕様。
    const input = 'price/or/gte/300/or/lte/150/_or_/color/lk/red/lk/blue/_or_/type/stationery';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'gte',
            value: '300',
            connector: 'or',
          },
          {
            operator: 'lte',
            value: '150',
            connector: 'or',
          },
        ],
        separator: '_and_', // 1つ目のフィールドは _and_ で固定（separaterは２つ目以上のフィールドがあって初めて機能するため）
      },
      {
        key: 'color',
        filters: [
          {
            operator: 'lk',
            value: 'red',
            connector: 'and',
          },
          {
            operator: 'lk',
            value: 'blue',
            connector: 'and',
          },
        ],
        separator: '_or_',
      },
      {
        key: 'type',
        filters: [
          {
            operator: 'eq',
            value: 'stationery',
            connector: 'or',
          },
        ],
        separator: '_or_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('裸の and をフィールド間 separator として解析できる', () => {
    // PHP 側 Field_Search::parse() と挙動を揃える: 裸の `and` は connector ではなく
    // フィールド間 separator として扱う。`_and_` を書かない URL でもフィールド境界として動作する。
    const input = 'content_language/2/and/information_hide/em/and/private/em';
    const expected: AcmsField[] = [
      {
        key: 'content_language',
        filters: [
          {
            operator: 'eq',
            value: '2',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
      {
        key: 'information_hide',
        filters: [
          {
            operator: 'em',
            value: '',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
      {
        key: 'private',
        filters: [
          {
            operator: 'em',
            value: '',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should handle complex expressions with different connectors', () => {
    const input = 'price/or/lt/100/or/gt/300/or/nem';
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            operator: 'lt',
            value: '100',
            connector: 'or',
          },
          {
            operator: 'gt',
            value: '300',
            connector: 'or',
          },
          {
            operator: 'nem',
            value: '',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('em 演算子のみのトークンは connector が and で値が空文字になる', () => {
    // PHP 側 parse_em演算子のみのトークンはconnectorがandで値が空文字になる と対称。
    // em は値を消費せず value='' を確定させ、connector はデフォルトの 'and' になる。
    const input = 'flag/em';
    const expected: AcmsField[] = [
      {
        key: 'flag',
        filters: [
          {
            operator: 'em',
            value: '',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('or 付き em 演算子は connector が or になる', () => {
    // PHP 側 parse_or付きem演算子はconnectorがorになる と対称。
    // 明示された 'or' トークンで connector='or' を確定させ、続く em が operator='em'/value='' を確定する。
    const input = 'flag/or/em';
    const expected: AcmsField[] = [
      {
        key: 'flag',
        filters: [
          {
            operator: 'em',
            value: '',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should handle escaped value', () => {
    const input = 'kataban/eq/PDW-850\\/1 SYM';
    const expected: AcmsField[] = [
      {
        key: 'kataban',
        filters: [
          {
            operator: 'eq',
            value: 'PDW-850\\/1 SYM',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];
    expect(AcmsFieldList.fromString(input).getFields()).toEqual(expected);
  });

  it('should be empty field array if "field" is not an array', () => {
    const formData = new FormData();
    formData.append('field', 'not-an-array');

    expect(AcmsFieldList.fromFormData(formData).getFields()).toEqual([]);
  });

  it('should be empty field array  if "field" is not an array of strings', () => {
    const formData = new FormData();
    formData.append('+field[]', '1');

    expect(AcmsFieldList.fromFormData(formData).getFields()).toEqual([]);
  });

  it('should parse FormData correctly and create an AcmsFieldList', () => {
    const formData = new FormData();
    formData.append('field[]', 'title');
    formData.append('title@operator[]', 'eq');
    formData.append('title@connector[]', 'and');
    formData.append('title[]', 'test');
    formData.append('title@separator', '_and_');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const expected: AcmsField[] = [
      {
        key: 'title',
        filters: [
          {
            value: 'test',
            operator: 'eq',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
    ];

    expect(fieldList.getFields()).toEqual(expected);
  });

  it('should handle multiple fields correctly', () => {
    const formData = new FormData();
    formData.append('field[]', 'title');
    formData.append('field[]', 'description');
    formData.append('title@operator[]', 'eq');
    formData.append('title@connector[]', 'and');
    formData.append('title[]', 'test');
    formData.append('title@separator', '_and_');
    formData.append('description@operator[]', 'neq');
    formData.append('description@connector[]', 'or');
    formData.append('description[]', 'sample');
    formData.append('description@separator', '_or_');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const expected: AcmsField[] = [
      {
        key: 'title',
        filters: [
          {
            value: 'test',
            operator: 'eq',
            connector: 'and',
          },
        ],
        separator: '_and_',
      },
      {
        key: 'description',
        filters: [
          {
            value: 'sample',
            operator: 'neq',
            connector: 'or',
          },
        ],
        separator: '_or_',
      },
    ];

    expect(fieldList.getFields()).toEqual(expected);
  });

  it('should handle same field key', () => {
    const formData = new FormData();
    formData.append('field[]', 'price');
    formData.append('field[]', 'price');
    formData.append('price@operator[]', 'gte');
    formData.append('price@connector[]', 'and');
    formData.append('price[]', '1000');
    formData.append('price@operator[]', 'lt');
    formData.append('price@connector[]', 'or');
    formData.append('price[]', '500');
    formData.append('price@separator', '_and_');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const expected: AcmsField[] = [
      {
        key: 'price',
        filters: [
          {
            value: '1000',
            operator: 'gte',
            connector: 'and',
          },
          {
            value: '500',
            operator: 'lt',
            connector: 'or',
          },
        ],
        separator: '_and_',
      },
    ];

    expect(fieldList.getFields()).toEqual(expected);
  });

  it('should handle empty filters correctly', () => {
    const formData = new FormData();
    formData.append('field[]', 'title');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const expected: AcmsField[] = [
      {
        key: 'title',
        filters: [],
      },
    ];

    expect(fieldList.getFields()).toEqual(expected);
  });

  it('should handle empty field correctly', () => {
    const formData = new FormData();
    formData.append('field[]', '');

    const fieldList = AcmsFieldList.fromFormData(formData);
    expect(fieldList.getFields()).toEqual([]);
  });

  // ---------------------------------------------------------------------------
  // fromFormData の追加カバレッジ（PHP 側 fromPost_* と同等の網羅を目指す）
  // ---------------------------------------------------------------------------

  it('fromFormData: 複数値で or_connector の検索条件を構築できる', () => {
    // PHP 側 fromPost_複数値でor_connectorの検索条件を構築できる と対称
    const formData = new FormData();
    formData.append('field[]', 'name');
    formData.append('name[]', '田中');
    formData.append('name[]', '鈴木');
    formData.append('name@operator[]', 'lk');
    formData.append('name@operator[]', 'lk');
    formData.append('name@connector[]', 'or');
    formData.append('name@connector[]', 'or');
    formData.append('name@separator', '_or_');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const expected: AcmsField[] = [
      {
        key: 'name',
        filters: [
          { value: '田中', operator: 'lk', connector: 'or' },
          { value: '鈴木', operator: 'lk', connector: 'or' },
        ],
        separator: '_or_',
      },
    ];

    expect(fieldList.getFields()).toEqual(expected);
    expect(fieldList.toString()).toBe('name/or/lk/田中/or/lk/鈴木');
  });

  it('fromFormData: 不正な operator は eq にフォールバックする', () => {
    // PHP 側 fromPost_不正なoperatorはeqにフォールバックする と対称
    const formData = new FormData();
    formData.append('field[]', 'age');
    formData.append('age[]', '30');
    formData.append('age@operator[]', 'invalid_op');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    expect(fields).toHaveLength(1);
    expect(fields[0].key).toBe('age');
    expect(fields[0].filters).toHaveLength(1);
    expect(fields[0].filters[0].operator).toBe('eq');
    expect(fields[0].filters[0].value).toBe('30');
    expect(fieldList.toString()).toBe('age/eq/30');
  });

  it('fromFormData: 空文字のキーはスキップされる', () => {
    // PHP 側 fromPost_空文字のキーはスキップされる と対称
    const formData = new FormData();
    formData.append('field[]', '');
    formData.append('field[]', 'age');
    formData.append('age[]', '30');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    expect(fields).toHaveLength(1);
    expect(fields[0].key).toBe('age');
    expect(fieldList.toString()).toBe('age/30');
  });

  it('fromFormData: 日本語のフィールドキーを受け入れる', () => {
    // PHP 側 fromPost_日本語のフィールドキーを受け入れる と対称
    const formData = new FormData();
    formData.append('field[]', '価格');
    formData.append('価格[]', '3000');
    formData.append('価格@operator[]', 'eq');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    expect(fields).toHaveLength(1);
    expect(fields[0].key).toBe('価格');
    expect(fields[0].filters).toEqual([{ value: '3000', operator: 'eq', connector: 'and' }]);
    expect(fieldList.toString()).toBe('価格/eq/3000');
  });

  it('fromFormData: 空白を含むキーも空文字でない限り受け入れる', () => {
    // PHP 側 fromPost_記号や空白を含むキーも空文字でない限り受け入れる と対称（部分対応）。
    // 注: TS 側は内部で parse-nested-form-data を使うため、dot 区切りキー（'price.tax'）は
    // ネストアクセサとして解釈されてしまい PHP と挙動が異なる（dot キーは現状未サポート）。
    // 空白を含むキーは受け入れ可能なのでそちらだけを検証する。
    const formData = new FormData();
    formData.append('field[]', 'name with space');
    formData.append('name with space[]', '田中');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    expect(fields.map((f) => f.key)).toEqual(['name with space']);
    expect(fieldList.toString()).toBe('name with space/田中');
  });

  it('fromFormData: 値が空のフィールドは filters が空配列になる', () => {
    // PHP 側 fromPost_値が空のフィールドはoperatorが追加されない と対称
    // PHP 側は _aryOperator に追加されない = filters が空。値がある側だけ serialize に出る。
    const formData = new FormData();
    formData.append('field[]', 'age');
    formData.append('field[]', 'name');
    formData.append('age[]', '30');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    const age = fields.find((f) => f.key === 'age');
    const name = fields.find((f) => f.key === 'name');

    expect(age?.filters).toHaveLength(1);
    expect(name?.filters).toEqual([]);
    expect(fieldList.toString()).toBe('age/30');
  });

  it('fromFormData: connector も operator も空の場合のデフォルト connector は or', () => {
    // PHP 側 fromPost_connector_operatorが両方空の場合デフォルトconnectorはor と対称
    const formData = new FormData();
    formData.append('field[]', 'tag');
    formData.append('tag[]', 'php');
    formData.append('tag[]', 'java');

    const fieldList = AcmsFieldList.fromFormData(formData);
    const fields = fieldList.getFields();

    expect(fields).toHaveLength(1);
    expect(fields[0].filters.map((f) => f.connector)).toEqual(['or', 'or']);
    expect(fieldList.toString()).toBe('tag/php/java');
  });

  it('fromFormData: 空の FormData は空の AcmsFieldList を返す', () => {
    // PHP 側 fromPost_空のPOSTは空のFieldSearchを返す と対称
    const formData = new FormData();

    const fieldList = AcmsFieldList.fromFormData(formData);

    expect(fieldList.getFields()).toEqual([]);
    expect(fieldList.toString()).toBe('');
  });
});
