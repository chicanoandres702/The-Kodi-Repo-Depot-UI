# The Kodi Repo Depot UI

Welcome to the Kodi Repo Depot UI—a sleek, web-based tool designed to make creating and managing your Kodi repositories a breeze. With its user-friendly interface, you'll find it easy to upload, organize, and maintain your add-ons.

## Features

* **Effortless Add-on Uploads:** Seamlessly upload Kodi add-ons in ZIP format using simple drag-and-drop or file selection.
* **Streamlined Repository Management:**
    * Configure your repository with custom settings like name, ID, version, and provider.
    * Easily add, delete, and monitor your uploaded add-ons through an organized interface.
    * Automatically generate essential `addons.xml` and `addons.xml.md5` files for your Kodi repositories.
* **Intuitive Web Interface:** Enjoy a clean and easy-to-navigate UI that simplifies repository management.

## Getting Started

1. **Clone the repository:**  
   ```bash
   git clone https://github.com/chicanoandres702/kodi-repo-depot-ui.git
   ```
2. **Upload to your web server:**  
   Transfer the contents of the repository to a PHP-supported web server.
3. **Access The Kodi Repo Depot UI:**  
   Open `index.php` in your web browser.
4. **Set up your repository:**  
   Enter your repository details (ID, name, version, etc.).
5. **Upload add-ons:**  
   Drag and drop or select ZIP files of your Kodi add-ons to upload them to your repository.
6. **Manage your add-ons:**  
   Easily view, delete, and manage your uploaded add-ons through the user-friendly interface.

## File Structure

* **index.php:** The main file that powers The Kodi Repo Depot UI.
* **styles.css:** The CSS file that styles the web interface.
* **repo/:** The directory where your uploaded add-ons are stored.

## Dependencies

* **PHP 7 or higher:** Ensure you have PHP installed with the `ZipArchive` extension enabled.
* **Web Server:** A web server like Apache or Nginx to host the PHP files.

## Contributing

We welcome contributions! Feel free to submit pull requests or open issues for bugs or feature requests.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
