## merge any project files in a directory into a single output file
import os
from typing import Iterable, Optional


def merge_files_from_directory(
    target_path: str,
    output_filename: str = "merged_output.txt",
    extensions: Optional[Iterable[str]] = None,
    max_size_bytes: int = 1_000_000,
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
    """

    # Convert to absolute path relative to current working directory
    base_dir = os.path.abspath(os.getcwd())
    search_dir = os.path.join(base_dir, target_path)

    if not os.path.isdir(search_dir):
        print(f"❌ Directory does not exist: {search_dir}")
        return

    output_path = os.path.join(search_dir, output_filename)

    allowed_exts = {e.lower() for e in extensions} if extensions is not None else None

    with open(output_path, "w", encoding="utf-8") as outfile:
        for root, dirs, files in os.walk(search_dir):
            for file in files:
                file_path = os.path.join(root, file)

                # Skip writing the output file into itself
                if file_path == output_path:
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

                try:
                    with open(file_path, "r", encoding="utf-8") as infile:
                        rel_path = os.path.relpath(file_path, search_dir)
                        outfile.write(f"\n=== File: {file} ===\n")
                        outfile.write(f"Path: {rel_path}\n")
                        outfile.write("---- File Content Start ----\n")
                        outfile.write(infile.read())
                        outfile.write("\n---- File Content End ----\n\n")
                except UnicodeDecodeError:
                    print(f"⏭️ Skipping {file_path}: not a text file")
                except Exception as e:
                    print(f"⚠️ Failed to read {file_path}: {e}")

    print(f"\n✅ All files merged into: {output_path}")

# Example usage:
if __name__ == "__main__":
    import sys

    if len(sys.argv) < 2 or len(sys.argv) > 3:
        print(
            "Usage: python merge_files.py <relative_directory> [ext1,ext2,...]"
        )
    else:
        ext_list = None
        if len(sys.argv) == 3 and sys.argv[2]:
            ext_list = [
                e if e.startswith(".") else f".{e}" for e in sys.argv[2].split(",")
            ]
        merge_files_from_directory(sys.argv[1], extensions=ext_list)
