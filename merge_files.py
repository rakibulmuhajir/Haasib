## merge any project files in a directory into a single output file
import os

def merge_files_from_directory(target_path, output_filename="merged_output.txt"):
    # Convert to absolute path relative to current working directory
    base_dir = os.path.abspath(os.getcwd())
    search_dir = os.path.join(base_dir, target_path)

    if not os.path.isdir(search_dir):
        print(f"❌ Directory does not exist: {search_dir}")
        return

    output_path = os.path.join(search_dir, output_filename)

    with open(output_path, "w", encoding="utf-8") as outfile:
        for root, dirs, files in os.walk(search_dir):
            for file in files:
                file_path = os.path.join(root, file)

                # Skip writing the output file into itself
                if file_path == output_path:
                    continue

                try:
                    with open(file_path, "r", encoding="utf-8") as infile:
                        rel_path = os.path.relpath(file_path, search_dir)
                        outfile.write(f"\n=== File: {file} ===\n")
                        outfile.write(f"Path: {rel_path}\n")
                        outfile.write("---- File Content Start ----\n")
                        outfile.write(infile.read())
                        outfile.write("\n---- File Content End ----\n\n")
                except Exception as e:
                    print(f"⚠️ Failed to read {file_path}: {e}")

    print(f"\n✅ All files merged into: {output_path}")

# Example usage:
if __name__ == "__main__":
    import sys
    if len(sys.argv) != 2:
        print("Usage: python merge_files.py <relative_directory>")
    else:
        merge_files_from_directory(sys.argv[1])
