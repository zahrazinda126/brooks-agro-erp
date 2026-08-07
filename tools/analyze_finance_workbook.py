import argparse
import json
from pathlib import Path

import openpyxl


def serialise(value):
    if hasattr(value, "isoformat"):
        return value.isoformat()
    if value is None or isinstance(value, (str, int, float, bool)):
        return value
    return str(value)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("workbook")
    parser.add_argument("output")
    args = parser.parse_args()

    source = Path(args.workbook)
    workbook = openpyxl.load_workbook(source, read_only=False, data_only=False, keep_vba=True)
    values = openpyxl.load_workbook(source, read_only=False, data_only=True, keep_vba=True)

    result = {
        "file": {"name": source.name, "size": source.stat().st_size},
        "properties": {
            "title": workbook.properties.title,
            "subject": workbook.properties.subject,
            "creator": workbook.properties.creator,
            "last_modified_by": workbook.properties.lastModifiedBy,
            "created": serialise(workbook.properties.created),
            "modified": serialise(workbook.properties.modified),
        },
        "defined_names": [],
        "sheets": [],
    }

    for name, definition in workbook.defined_names.items():
        result["defined_names"].append({
            "name": name,
            "value": definition.attr_text,
            "hidden": definition.hidden,
        })

    for sheet in workbook.worksheets:
        value_sheet = values[sheet.title]
        formulas = []
        nonempty = []
        errors = []
        # _cells contains only physically stored cells. iter_rows() would walk
        # millions of empty formatted cells in several workbook templates.
        for cell in sheet._cells.values():
            if cell.value is None:
                continue
            cached = value_sheet[cell.coordinate].value
            if isinstance(cell.value, str) and cell.value.startswith("="):
                formulas.append({"cell": cell.coordinate, "formula": cell.value, "cached": serialise(cached)})
                if isinstance(cached, str) and cached.startswith("#"):
                    errors.append({"cell": cell.coordinate, "error": cached})
            if len(nonempty) < 250:
                nonempty.append({"cell": cell.coordinate, "value": serialise(cell.value), "cached": serialise(cached)})

        result["sheets"].append({
            "name": sheet.title,
            "state": sheet.sheet_state,
            "dimensions": sheet.calculate_dimension(),
            "max_row": sheet.max_row,
            "max_column": sheet.max_column,
            "freeze_panes": str(sheet.freeze_panes) if sheet.freeze_panes else None,
            "auto_filter": sheet.auto_filter.ref,
            "print_area": str(sheet.print_area) if sheet.print_area else None,
            "print_titles": sheet.print_title_rows,
            "merged_ranges": [str(item) for item in sheet.merged_cells.ranges],
            "formula_count": len(formulas),
            "formula_errors": errors,
            "formulas": formulas,
            "sample_cells": nonempty,
        })

    Path(args.output).write_text(json.dumps(result, indent=2, default=serialise), encoding="utf-8")
    print(json.dumps({
        "sheets": [{"name": item["name"], "state": item["state"], "dimensions": item["dimensions"], "formulas": item["formula_count"], "errors": len(item["formula_errors"])} for item in result["sheets"]],
        "defined_names": len(result["defined_names"]),
    }, indent=2))


if __name__ == "__main__":
    main()
