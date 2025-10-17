## merge any project files in a directory into a single output file
import argparse
import math
import os
from typing import Iterable, Optional, Sequence, Tuple


def _parse_fraction(spec: str) -> Tuple[int, int]:
    parts = spec.split("/", maxsplit=1)
    if len(parts) != 2:
        raise ValueError(
            "Fractions must follow '<numerator>/<denominator>' (e.g. '1/2')."
        )

    numerator_str, denominator_str = parts[0].strip(), parts[1].strip()
    if not numerator_str or not denominator_str:
        raise ValueError("Both numerator and denominator are required (e.g. '1/2').")

    try:
        numerator = int(numerator_str)
        denominator = int(denominator_str)
    except ValueError:
        raise ValueError("Fraction values must be integers (e.g. '3/5').") from None

    if denominator <= 0:
        raise ValueError("Fraction denominator must be greater than zero.")

    if numerator <= 0:
        raise ValueError("Fraction numerator must be greater than zero.")

    if numerator > denominator:
        raise ValueError("Fraction numerator cannot exceed denominator.")

    return numerator, denominator


def _parse_portion_spec(spec: str) -> Tuple[int, int, Optional[int]]:
    """Return start segment, denominator, and optional end segment."""
    if ":" in spec:
        start_part, end_part = spec.split(":", maxsplit=1)
        start_numerator, denominator = _parse_fraction(start_part.strip())

        end_part = end_part.strip()
        if end_part:
            end_numerator, end_denominator = _parse_fraction(end_part)
            if end_denominator != denominator:
                raise ValueError("Start and end fractions must share the same denominator.")
        else:
            end_numerator = None
    else:
        end_numerator, denominator = _parse_fraction(spec.strip())
        start_numerator = 1

    if start_numerator > denominator:
        raise ValueError("Portion start numerator cannot exceed denominator.")

    if end_numerator is not None and end_numerator > denominator:
        raise ValueError("Portion end numerator cannot exceed denominator.")

    if end_numerator is not None and end_numerator < start_numerator:
        raise ValueError("Portion end numerator must be greater than or equal to the start numerator.")

    return start_numerator, denominator, end_numerator


def _format_extensions(extensions: Optional[Sequence[str]]) -> Optional[set]:
    if extensions is None:
        return None
    return {ext.lower() for ext in extensions}


def merge_files_from_directory(
    target_path: str,
    output_filename: str = "merged_output.txt",
    extensions: Optional[Iterable[str]] = None,
    max_size_bytes: int = 1_000_000,
    portion_spec: Optional[str] = None,
) -> None:
    """Merge text files from ``target_path`` into ``output_filename``.

    Parameters
    ----------
    target_path:
        Relative path to the directory containing files to merge.
    output_filename:
        Name of the output file to create inside ``target_path``.
    extensions:
        Optional iterable of allowed file extensions (e.g., [".py", ".txt"]).
        If provided, files whose extensions are not in the list are skipped.
    max_size_bytes:
        Maximum file size to include. Files larger than this will be skipped.
    portion_spec:
        Optional string fraction such as "1/2" or "3/5". The numerator indicates
        how many equal parts to include from the start when the eligible files
        are divided into the number of parts defined by the denominator.
    """

    base_dir = os.path.abspath(os.getcwd())
    search_dir = os.path.join(base_dir, target_path)

    if not os.path.isdir(search_dir):
        print(f"❌ Directory does not exist: {search_dir}")
        return

    output_base, output_ext = os.path.splitext(output_filename)

    def _is_generated_output(name: str) -> bool:
        if name == output_filename:
            return True
        if output_base:
            if name.startswith(f"{output_base}_"):
                return bool(output_ext) and name.endswith(output_ext) or not output_ext
        return False

    allowed_exts = _format_extensions(extensions)
    eligible_files = []

    for root, dirs, files in os.walk(search_dir):
        dirs.sort()
        files.sort()
        for file in files:
            file_path = os.path.join(root, file)

            if _is_generated_output(file):
                continue

            ext = os.path.splitext(file)[1].lower()
            if allowed_exts is not None and ext not in allowed_exts:
                print(f"⏭️ Skipping {file_path}: extension '{ext}' not allowed")
                continue

            try:
                size = os.path.getsize(file_path)
            except OSError as e:
                print(f"⚠️ Unable to access {file_path}: {e}")
                continue

            if size > max_size_bytes:
                print(
                    f"⏭️ Skipping {file_path}: size {size} exceeds {max_size_bytes} bytes"
                )
                continue

            rel_path = os.path.relpath(file_path, search_dir)
            eligible_files.append((file, rel_path, file_path))

    if not eligible_files:
        print("⚠️ No eligible files found to merge.")
        return

    eligible_files.sort(key=lambda item: item[1])
    total_eligible = len(eligible_files)
    slice_start_index = 0
    slice_end_index = total_eligible
    selected_files = eligible_files

    if portion_spec:
        try:
            start_segment, denominator, end_segment = _parse_portion_spec(portion_spec)
        except ValueError as exc:
            print(f"❌ {exc}")
            return

        start_index = math.floor((start_segment - 1) * total_eligible / denominator)
        end_index = (
            total_eligible
            if end_segment is None
            else math.ceil(end_segment * total_eligible / denominator)
        )

        if start_index >= total_eligible:
            print("⚠️ Portion start exceeds available files. Nothing to merge.")
            return

        end_index = max(start_index, min(total_eligible, end_index))
        selected_files = eligible_files[start_index:end_index]
        slice_start_index = start_index
        slice_end_index = end_index

        if not selected_files:
            print("⚠️ Portion selection resulted in zero files. Nothing to merge.")
            return

        if end_segment is None:
            portion_label = f"{start_segment}/{denominator} through end"
        elif start_segment == 1:
            portion_label = f"first {end_segment}/{denominator}"
        elif start_segment == end_segment:
            portion_label = f"{start_segment}/{denominator}"
        else:
            portion_label = f"{start_segment}/{denominator} through {end_segment}/{denominator}"

        print(
            f"ℹ️ Portion {portion_label}: merging {len(selected_files)} "
            f"of {total_eligible} eligible files."
        )
    else:
        print(f"ℹ️ Merging all {total_eligible} eligible files.")
        selected_files = eligible_files

    start_label = slice_start_index + 1
    end_label = slice_end_index
    suffix = f"_{start_label}-{end_label}" if selected_files else ""
    if output_base:
        final_output_name = f"{output_base}{suffix}{output_ext}"
    else:
        final_output_name = f"{output_filename}{suffix}"

    output_path = os.path.join(search_dir, final_output_name)

    with open(output_path, "w", encoding="utf-8") as outfile:
        for file_name, rel_path, file_path in selected_files:
            try:
                with open(file_path, "r", encoding="utf-8") as infile:
                    outfile.write(f"\n=== File: {file_name} ===\n")
                    outfile.write(f"Path: {rel_path}\n")
                    outfile.write("---- File Content Start ----\n")
                    outfile.write(infile.read())
                    outfile.write("\n---- File Content End ----\n\n")
            except UnicodeDecodeError:
                print(f"⏭️ Skipping {file_path}: not a text file")
            except Exception as e:
                print(f"⚠️ Failed to read {file_path}: {e}")

    print(f"\n✅ All files merged into: {output_path}")


def _comma_separated_extensions(ext_string: Optional[str]) -> Optional[Iterable[str]]:
    if not ext_string:
        return None
    raw_exts = [item.strip() for item in ext_string.split(",") if item.strip()]
    if not raw_exts:
        return None
    return [
        ext if ext.startswith(".") else f".{ext}"
        for ext in raw_exts
    ]


def _build_argument_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Merge text files from a directory into a single output file."
    )
    parser.add_argument(
        "target_path",
        help="Relative path to the directory whose files should be merged.",
    )
    parser.add_argument(
        "-e",
        "--extensions",
        help="Comma-separated list of allowed file extensions (e.g. 'py,txt').",
    )
    parser.add_argument(
        "-p",
        "--portion",
        help=(
            "Fractional slice of eligible files to merge (e.g. '1/2' for the first half, "
            "'3/5:4/5' for the third through fourth fifths, '3/5:' for the last three fifths)."
        ),
    )
    parser.add_argument(
        "-o",
        "--output",
        default="merged_output.txt",
        help="Name of the merged output file (defaults to 'merged_output.txt').",
    )
    parser.add_argument(
        "-m",
        "--max-size",
        type=int,
        default=1_000_000,
        help="Maximum file size in bytes to include (defaults to 1,000,000).",
    )
    return parser


if __name__ == "__main__":
    parser = _build_argument_parser()
    args = parser.parse_args()

    ext_list = _comma_separated_extensions(args.extensions)

    merge_files_from_directory(
        args.target_path,
        output_filename=args.output,
        extensions=ext_list,
        max_size_bytes=args.max_size,
        portion_spec=args.portion,
    )
