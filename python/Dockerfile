# Use an official Python runtime as a parent image
FROM python:3.13-slim

# Set the working directory in the container
WORKDIR /app

# Copy the current directory contents into the container
COPY . .

RUN apt-get update && apt-get install -y \
    build-essential \
    curl \
    software-properties-common \
    && rm -rf /var/lib/apt/lists/*

# Install dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Expose the application port
EXPOSE 9080 9999

# Command to run the application
CMD ["streamlit", "run", "streamlit_ui.py", "--server.port=9080", "--server.address=0.0.0.0"]